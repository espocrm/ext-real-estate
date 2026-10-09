<?php
namespace Espo\Modules\RealEstate\Tools\Commission;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\ConflictSilent;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PDOException;
use RuntimeException;

/** Server-authoritative commission revision creation. */
class CommissionRevisionCreator
{
    private const INPUT = [
        'offerCycleId', 'sourceQuoteId', 'method', 'percentage', 'fixedAmount',
        'baseAmount', 'baseSemantic', 'currency', 'payer', 'conditions',
        'effectiveFrom', 'effectiveTo', 'policyRevision', 'sourceEvidenceRefs', 'notes', 'requestId',
    ];

    /** True when the last create() returned an already-committed revision. */
    private bool $replayed = false;
    /** True when that replay was resolved after a unique-key collision. */
    private bool $replayedOnCollision = false;

    public function wasReplayed(): bool { return $this->replayed; }
    public function wasReplayedOnCollision(): bool { return $this->replayedOnCollision; }

    public function __construct(private EntityManager $em, private Acl $acl) {}

    public function create(array $input): Entity
    {
        $this->replayed = false;
        $this->replayedOnCollision = false;

        if ($this->acl->getLevel('NgCommissionRevision', 'create') === Table::LEVEL_NO) {
            throw new Forbidden();
        }

        $v = array_intersect_key($input, array_flip(self::INPUT));
        $v['requestId'] = $this->validateRequestId($v['requestId'] ?? null);
        $offerCycleId = $v['offerCycleId'] ?? null;
        $sourceQuoteId = $v['sourceQuoteId'] ?? null;
        if (!$offerCycleId && !$sourceQuoteId) {
            throw new BadRequest('Commission requires an OfferCycle or SourceQuote parent.');
        }

        // Validate type/precision BEFORE any canonical comparison so a malformed
        // value can never be truncated into a "valid" replay payload.
        $method = (string) ($v['method'] ?? '');
        if (!in_array($method, ['percentage', 'fixed', 'other-evidenced'], true)) {
            throw new BadRequest('Invalid commission method.');
        }
        if (!in_array($v['currency'] ?? null, ['VND', 'USD'], true)) {
            throw new BadRequest('Commission currency is required and invalid.');
        }
        if (!in_array($v['payer'] ?? null, ['owner', 'buyer', 'other-party-snapshot'], true)) {
            throw new BadRequest('Commission payer is required and invalid.');
        }
        $this->validateDecimal($v['percentage'] ?? null, 'percentage', false);
        $this->validateDecimal($v['fixedAmount'] ?? null, 'fixedAmount', false);
        $this->validateDecimal($v['baseAmount'] ?? null, 'baseAmount', false);

        if ($method === 'percentage' && ($v['percentage'] ?? null) === null) {
            throw new BadRequest('Percentage commission requires percentage.');
        }
        if ($method === 'fixed' && ($v['fixedAmount'] ?? null) === null) {
            throw new BadRequest('Fixed commission requires fixedAmount.');
        }
        if ($method === 'percentage' && ($v['baseAmount'] ?? null) === null) {
            throw new BadRequest('Percentage commission requires baseAmount.');
        }

        // Single replay path. Used by both the pre-check and the post-collision
        // branch so every returned revision passes the same guard: original
        // parent exists, current ACL, canonical payload.
        if ($v['requestId'] !== null) {
            $replay = $this->resolveReplay($v['requestId'], $v);
            if ($replay !== null) { $this->replayed = true; return $replay; }
        }

        $cycle = $offerCycleId ? $this->em->getEntityById('NgOfferCycle', (string) $offerCycleId) : null;
        $quote = $sourceQuoteId ? $this->em->getEntityById('NgSourceQuote', (string) $sourceQuoteId) : null;
        if (($offerCycleId && !$cycle) || ($sourceQuoteId && !$quote)) {
            throw new NotFoundSilent();
        }
        if (($cycle && (!$this->acl->checkEntityRead($cycle) || !$this->acl->checkEntityEdit($cycle))) ||
            ($quote && (!$this->acl->checkEntityRead($quote) || !$this->acl->checkEntityEdit($quote)))) {
            throw new NotFoundSilent();
        }
        if ($cycle && $quote && (string) $quote->get('offerCycleId') !== (string) $cycle->getId()) {
            throw new NotFoundSilent();
        }
        // Server derives ownership from the parent; client-supplied assignedUser
        // is never trusted (H02 forged-assignedUser guard).
        $parentEntity = $cycle ?: $quote;
        $ownerId = $parentEntity->get('assignedUserId');
        if ($ownerId) {
            $v['assignedUserId'] = (string) $ownerId;
        }

        // expectedAmount is derived only from validated server input. Client-supplied
        // expectedAmount was excluded from INPUT and can never be persisted.
        $v['expectedAmount'] = $this->deriveExpectedAmount($method, $v);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->attempt($v);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), '1062') && !str_contains($e->getMessage(), '23000')) throw $e;
                usleep(50000 * $attempt);
                // Concurrent duplicate landed. Never return a raw row here —
                // re-read and revalidate through the same guarded replay.
                if ($v['requestId'] !== null) {
                    $replay = $this->resolveReplay($v['requestId'], $v);
                    if ($replay !== null) {
                        $this->replayed = true;
                        $this->replayedOnCollision = true;
                        return $replay;
                    }
                }
            }
        }
        throw new RuntimeException('Commission revision collision retry exhausted.');
    }

    /**
     * Resolve an existing revision for a requestId through the full guard.
     * Returns null when no revision exists yet. Denies (NotFoundSilent) when the
     * revision is soft-deleted or any original parent is missing / no longer
     * authorized, and rejects (ConflictSilent) on canonical payload mismatch.
     */
    private function resolveReplay(string $requestId, array $v): ?Entity
    {
        // The lookup deliberately includes soft-deleted rows. That is what keeps
        // write-count idempotency intact: the key stays held, so a retried key
        // can never fall through to the append path and write a SECOND revision
        // for the same request. Denial below is about returning a deleted row —
        // not about how many rows exist, which stays exactly one.
        $existing = $this->em->getRDBRepository('NgCommissionRevision')
            ->where(['requestId' => $requestId, 'deleted' => [0, 1]])
            ->findOne();

        if (!$existing) return null;

        // FX2: a deleted revision is a neutral denial — no id, no payload, no
        // append, no restore. Checked first so the answer does not depend on
        // ACL, parent state or payload, and the deleted row cannot be reached
        // through a replay.
        if ($this->isDeleted($existing)) {
            throw new NotFoundSilent();
        }

        // Current permission: entity-level create must still be allowed.
        if ($this->acl->getLevel('NgCommissionRevision', 'create') === Table::LEVEL_NO) {
            throw new Forbidden();
        }

        // Every original parent recorded on the revision must exist now and be
        // readable+editable by the current requester. A soft-deleted / missing
        // parent yields null from getEntityById -> deny.
        $origCycle = $existing->get('offerCycleId');
        $origQuote = $existing->get('sourceQuoteId');
        if ($origCycle) {
            $cycle = $this->em->getEntityById('NgOfferCycle', (string) $origCycle);
            if (!$cycle || !$this->acl->checkEntityRead($cycle) || !$this->acl->checkEntityEdit($cycle)) {
                throw new NotFoundSilent();
            }
        }
        if ($origQuote) {
            $quote = $this->em->getEntityById('NgSourceQuote', (string) $origQuote);
            if (!$quote || !$this->acl->checkEntityRead($quote) || !$this->acl->checkEntityEdit($quote)) {
                throw new NotFoundSilent();
            }
        }

        if (!$this->payloadMatches($existing, $v)) {
            throw new ConflictSilent('requestId was already used with a different payload.');
        }

        return $existing;
    }

    private function validateRequestId(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $value)) {
            throw new BadRequest('requestId must be 8-64 ASCII identity characters.');
        }
        return $value;
    }

    /** Soft-deleted rows are never replayed (FX2). */
    private function isDeleted(Entity $entity): bool
    {
        $value = $entity->get('deleted');

        return $value === true || $value === 1 || $value === '1';
    }

    private function canonicalDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $str = (string) $value;
        // Strict: never truncate an over-precision value into a "valid" form.
        if (!preg_match('/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/', $str)) {
            throw new BadRequest('Decimal value has invalid format or precision.');
        }
        [$whole, $fraction] = array_pad(explode('.', $str, 2), 2, '');
        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    private function payloadMatches(Entity $existing, array $v): bool
    {
        $incoming = [
            // Parent IDs are bound into the canonical payload so a global
            // requestId lookup can never replay a revision from a different
            // parent (H05 cross-parent guard).
            'offerCycleId' => ($v['offerCycleId'] ?? null) === '' ? null : ($v['offerCycleId'] ?? null),
            'sourceQuoteId' => ($v['sourceQuoteId'] ?? null) === '' ? null : ($v['sourceQuoteId'] ?? null),
            'method' => (string) ($v['method'] ?? ''),
            'percentage' => $this->canonicalDecimal($v['percentage'] ?? null),
            'fixedAmount' => $this->canonicalDecimal($v['fixedAmount'] ?? null),
            'baseAmount' => $this->canonicalDecimal($v['baseAmount'] ?? null),
            'baseSemantic' => ($v['baseSemantic'] ?? null) === '' ? null : ($v['baseSemantic'] ?? null),
            'currency' => (string) ($v['currency'] ?? ''),
            'payer' => (string) ($v['payer'] ?? ''),
            'conditions' => ($v['conditions'] ?? null) === '' ? null : ($v['conditions'] ?? null),
            'effectiveFrom' => ($v['effectiveFrom'] ?? null) === '' ? null : ($v['effectiveFrom'] ?? null),
            'effectiveTo' => ($v['effectiveTo'] ?? null) === '' ? null : ($v['effectiveTo'] ?? null),
            'policyRevision' => ($v['policyRevision'] ?? null) === '' ? null : ($v['policyRevision'] ?? null),
            'sourceEvidenceRefs' => ($v['sourceEvidenceRefs'] ?? null) === '' ? null : ($v['sourceEvidenceRefs'] ?? null),
            'notes' => ($v['notes'] ?? null) === '' ? null : ($v['notes'] ?? null),
            // expectedAmount is server-derived, so it is excluded from the
            // canonical comparison — client cannot supply it.
        ];
        $stored = [
            'offerCycleId' => $existing->get('offerCycleId') === '' ? null : $existing->get('offerCycleId'),
            'sourceQuoteId' => $existing->get('sourceQuoteId') === '' ? null : $existing->get('sourceQuoteId'),
            'method' => (string) $existing->get('method'),
            'percentage' => $this->canonicalDecimal($existing->get('percentage')),
            'fixedAmount' => $this->canonicalDecimal($existing->get('fixedAmount')),
            'baseAmount' => $this->canonicalDecimal($existing->get('baseAmount')),
            'baseSemantic' => $existing->get('baseSemantic') === '' ? null : $existing->get('baseSemantic'),
            'currency' => (string) $existing->get('currency'),
            'payer' => (string) $existing->get('payer'),
            'conditions' => $existing->get('conditions') === '' ? null : $existing->get('conditions'),
            'effectiveFrom' => $existing->get('effectiveFrom') === '' ? null : $existing->get('effectiveFrom'),
            'effectiveTo' => $existing->get('effectiveTo') === '' ? null : $existing->get('effectiveTo'),
            'policyRevision' => $existing->get('policyRevision') === '' ? null : $existing->get('policyRevision'),
            'sourceEvidenceRefs' => $existing->get('sourceEvidenceRefs') === '' ? null : $existing->get('sourceEvidenceRefs'),
            'notes' => $existing->get('notes') === '' ? null : $existing->get('notes'),
            // expectedAmount is server-derived, so it is excluded from the
            // canonical comparison.
        ];
        return $incoming === $stored;
    }

    private function attempt(array $v): Entity
    {
        $tx = $this->em->getTransactionManager();
        if ($tx->isStarted()) throw new RuntimeException('Outer transaction forbidden.');
        $out = null;
        $tx->run(function () use ($v, &$out): void {
            $parentType = !empty($v['offerCycleId']) ? 'NgOfferCycle' : 'NgSourceQuote';
            $parentId = (string) ($v['offerCycleId'] ?? $v['sourceQuoteId']);
            $this->em->getRDBRepository($parentType)->forUpdate()->where(['id' => $parentId])->findOne();
            $where = [
                'offerCycleId' => $v['offerCycleId'] ?? null,
                'sourceQuoteId' => $v['sourceQuoteId'] ?? null,
            ];
            $max = $this->em->getRDBRepository('NgCommissionRevision')
                ->where($where + ['deleted' => [0, 1]])
                ->max('revisionNumber');
            $head = $this->em->getRDBRepository('NgCommissionRevision')
                ->where($where + ['deleted' => false])
                ->order('revisionNumber', 'DESC')
                ->findOne();
            // requestId deduplication already happened in create() before
            // parent validation; here we only append a new revision.
            $entity = $this->em->getNewEntity('NgCommissionRevision');
            foreach (self::INPUT as $field) if (array_key_exists($field, $v)) $entity->set($field, $v[$field]);
            // Server-derived ownership (H02): never read assignedUserId from the
            // request body directly, only from the validated parent entity.
            if (isset($v['assignedUserId'])) {
                $entity->set('assignedUserId', $v['assignedUserId']);
            }
            $entity->set('expectedAmount', $v['expectedAmount']);
            $entity->set('revisionNumber', $max === null ? 1 : (int) $max + 1);
            if ($head) $entity->set('predecessorId', $head->getId());
            $entity->set('publicEligibility', false);
            $this->em->saveEntity($entity, ['silent' => true, 'noStream' => true, 'noNotifications' => true]);
            $out = $entity;
        });
        return $out;
    }

    private function deriveExpectedAmount(string $method, array $v): string
    {
        if ($method === 'other-evidenced') {
            throw new BadRequest('Derived amount is not defined for other-evidenced commission.');
        }
        if (!function_exists('bcadd') || !function_exists('bcmul') || !function_exists('bcdiv')) {
            throw new RuntimeException('Decimal calculator unavailable; derived amount denied.');
        }
        $derived = $method === 'fixed'
            ? $this->normalizeDecimal((string) $v['fixedAmount'])
            : $this->normalizeDecimal(bcdiv(bcmul(
                $this->normalizeDecimal((string) $v['percentage']),
                $this->normalizeDecimal((string) $v['baseAmount']),
                8
            ), '100', 2));
        // R4: derived value must fit the decimal(18,2) column; reject as 400
        // instead of letting MySQL throw a 500 on insert.
        if (!preg_match('/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/', $derived)) {
            throw new BadRequest('Derived amount exceeds supported precision.');
        }
        return $derived;
    }

    private function validateDecimal(mixed $value, string $field, bool $required): void
    {
        if ($value === null || $value === '') {
            if ($required) throw new BadRequest("{$field} is required.");
            return;
        }
        if (!is_string($value) && !is_int($value)) throw new BadRequest("{$field} must be a fixed-scale decimal string.");
        if (!preg_match('/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/', (string) $value)) throw new BadRequest("{$field} has invalid decimal format.");
    }

    private function normalizeDecimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        return $whole . '.' . $fraction;
    }
}
