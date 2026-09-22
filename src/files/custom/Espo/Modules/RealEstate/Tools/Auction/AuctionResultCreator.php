<?php
namespace Espo\Modules\RealEstate\Tools\Auction;

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

/** Server-authoritative, serialized auction-result revision creation. */
class AuctionResultCreator
{
    private const MAX_ATTEMPTS = 3;
    private const INPUT = ['lotId', 'resultState', 'winningAmount', 'currency', 'priceUnit', 'notes', 'requestId'];

    /** True when the last create() returned an already-committed revision. */
    private bool $replayed = false;
    /** True when that replay was resolved after a unique-key collision. */
    private bool $replayedOnCollision = false;

    public function wasReplayed(): bool { return $this->replayed; }
    public function wasReplayedOnCollision(): bool { return $this->replayedOnCollision; }

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl
    ) {}

    public function create(array $values): Entity
    {
        $this->replayed = false;
        $this->replayedOnCollision = false;

        if ($this->acl->getLevel('NgAuctionResult', 'create') === Table::LEVEL_NO) {
            throw new Forbidden();
        }

        $values = array_intersect_key($values, array_flip(self::INPUT));
        $values['requestId'] = $this->validateRequestId($values['requestId'] ?? null);
        $lotId = $values['lotId'] ?? null;
        if (!$lotId) throw new BadRequest('NgAuctionResult requires lotId.');

        // Validate type/precision BEFORE any canonical comparison so a
        // malformed value can never be truncated into a "valid" replay match.
        $state = (string) ($values['resultState'] ?? '');
        if (!in_array($state, ['no-result', 'won', 'cancelled', 'correction'], true)) {
            throw new BadRequest('Invalid AuctionResult resultState.');
        }
        // winningAmount, when present, is always type/precision-validated —
        // regardless of state — so a malformed value can never round into a
        // canonical replay match.
        $amountPresent = array_key_exists('winningAmount', $values)
            && $values['winningAmount'] !== null && $values['winningAmount'] !== '';
        if ($amountPresent) {
            if (!is_string($values['winningAmount']) && !is_int($values['winningAmount'])) {
                throw new BadRequest('winningAmount must be a decimal string or integer.');
            }
            if (!preg_match('/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/', (string) $values['winningAmount'])) {
                throw new BadRequest('winningAmount has invalid decimal format.');
            }
        }
        if ($state === 'won') {
            if (!$amountPresent || !array_key_exists('currency', $values) || !array_key_exists('priceUnit', $values)) {
                throw new BadRequest('Won result requires winningAmount, currency and priceUnit.');
            }
            if (!in_array($values['currency'], ['VND', 'USD'], true) || !in_array($values['priceUnit'], ['total', 'per_m2'], true)) {
                throw new BadRequest('Invalid currency or priceUnit.');
            }
        }
        if ($state === 'no-result' && $amountPresent) {
            throw new BadRequest('No-result cannot contain winningAmount.');
        }

        // Single replay path. Used by both the pre-check and the post-collision
        // branch so every returned revision passes the same guard: parent
        // exists, current ACL, canonical payload.
        if ($values['requestId'] !== null) {
            $replay = $this->resolveReplay($values['requestId'], $values);
            if ($replay !== null) { $this->replayed = true; return $replay; }
        }

        $lot = $this->entityManager->getEntityById('NgAuctionLot', (string) $lotId);
        if (!$lot || !$this->acl->checkEntityRead($lot)) throw new NotFoundSilent();
        // Server derives ownership from the owning lot; client-supplied
        // assignedUserId is never trusted (H02 forged-assignedUser guard).
        $ownerId = $lot->get('assignedUserId');
        if ($ownerId) {
            $values['assignedUserId'] = (string) $ownerId;
        }

        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try { return $this->tryCreate($values); }
            catch (PDOException $e) {
                $lastError = $e;
                if (!str_contains($e->getMessage(), '1062') && !str_contains($e->getMessage(), '23000')) throw $e;
                usleep(50_000 * $attempt);
                // Concurrent duplicate landed. Never return a raw row here —
                // go back through the same guarded replay resolution.
                if ($values['requestId'] !== null) {
                    $replay = $this->resolveReplay($values['requestId'], $values);
                    if ($replay !== null) {
                        $this->replayed = true;
                        $this->replayedOnCollision = true;
                        return $replay;
                    }
                }
            }
        }
        throw new RuntimeException('NgAuctionResult revision allocation failed: '.$lastError?->getMessage());
    }

    /**
     * Resolve an existing revision for a requestId through the full guard.
     * Returns null when no revision exists yet. Denies (NotFoundSilent) when
     * the revision is soft-deleted or its original parent is missing or no
     * longer authorized, and rejects (ConflictSilent) on canonical payload
     * mismatch.
     */
    private function resolveReplay(string $requestId, array $values): ?Entity
    {
        // The lookup deliberately includes soft-deleted rows. That is what keeps
        // write-count idempotency intact: the key stays held, so a retried key
        // can never fall through to the append path and write a SECOND revision
        // for the same request. Denial below is about returning a deleted row —
        // not about how many rows exist, which stays exactly one.
        $existing = $this->entityManager->getRDBRepository('NgAuctionResult')
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
        if ($this->acl->getLevel('NgAuctionResult', 'create') === Table::LEVEL_NO) {
            throw new Forbidden();
        }

        // Original parent must exist and be authorized now. A soft-deleted or
        // missing lot yields null from getEntityById -> deny.
        $lot = $this->entityManager->getEntityById('NgAuctionLot', (string) $existing->get('lotId'));
        if (!$lot || !$this->acl->checkEntityRead($lot)) {
            throw new NotFoundSilent();
        }

        if (!$this->payloadMatches($existing, $values)) {
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

    private function payloadMatches(Entity $existing, array $values): bool
    {
        $incoming = [
            'lotId' => (string) ($values['lotId'] ?? ''),
            'resultState' => (string) ($values['resultState'] ?? ''),
            'winningAmount' => $this->canonicalDecimal($values['winningAmount'] ?? null),
            'currency' => isset($values['currency']) ? (string) $values['currency'] : null,
            'priceUnit' => isset($values['priceUnit']) ? (string) $values['priceUnit'] : null,
            'notes' => ($values['notes'] ?? null) === '' ? null : ($values['notes'] ?? null),
        ];
        $stored = [
            'lotId' => (string) $existing->get('lotId'),
            'resultState' => (string) $existing->get('resultState'),
            'winningAmount' => $this->canonicalDecimal($existing->get('winningAmount')),
            'currency' => $existing->get('currency') !== null ? (string) $existing->get('currency') : null,
            'priceUnit' => $existing->get('priceUnit') !== null ? (string) $existing->get('priceUnit') : null,
            'notes' => $existing->get('notes') === '' ? null : $existing->get('notes'),
        ];
        return $incoming === $stored;
    }

    private function tryCreate(array $values): Entity
    {
        $tx = $this->entityManager->getTransactionManager();
        if ($tx->isStarted()) throw new RuntimeException('Outer transaction is forbidden for result create.');
        $result = null;
        $tx->run(function () use ($values, &$result): void {
            $this->entityManager->getRDBRepository('NgAuctionLot')->forUpdate()->where(['id' => $values['lotId']])->findOne();
            // H05: revision number must increment from max revision (including soft-deleted)
            // to avoid collision with unique index. Predecessor links to latest non-deleted.
            $max = $this->entityManager->getRDBRepository('NgAuctionResult')
                ->where(['lotId' => $values['lotId'], 'deleted' => [0, 1]])
                ->max('revisionNumber');
            $head = $this->entityManager->getRDBRepository('NgAuctionResult')
                ->where(['lotId' => $values['lotId'], 'deleted' => false])
                ->order('revisionNumber', 'DESC')
                ->findOne();
            $entity = $this->entityManager->getNewEntity('NgAuctionResult');
            foreach (self::INPUT as $field) if (array_key_exists($field, $values)) $entity->set($field, $values[$field]);
            if (isset($values['assignedUserId'])) {
                $entity->set('assignedUserId', $values['assignedUserId']);
            }
            $entity->set('revisionNumber', $max === null ? 1 : (int) $max + 1);
            if ($head) $entity->set('predecessorId', $head->getId());
            $this->entityManager->saveEntity($entity, ['silent' => true, 'noStream' => true, 'noNotifications' => true]);
            $result = $entity;
        });
        return $result;
    }
}
