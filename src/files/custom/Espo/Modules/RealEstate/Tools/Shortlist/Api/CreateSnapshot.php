<?php
namespace Espo\Modules\RealEstate\Tools\Shortlist\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Bounded sent-snapshot create action; generic CRUD create stays denied. */
class CreateSnapshot implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user
    ) {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody();
        if (!$data || !is_object($data)) throw new BadRequest('Missing snapshot payload.');
        $input = get_object_vars($data);
        $allowed = ['shortlistId', 'quoteId', 'selectorStatus', 'amount', 'currency', 'unit', 'scenario', 'asOf', 'ruleVersion', 'channel'];
        $values = array_intersect_key($input, array_flip($allowed));
        foreach (['shortlistId', 'quoteId', 'selectorStatus'] as $field) {
            if (empty($values[$field])) throw new BadRequest("{$field} is required.");
        }
        if (!in_array($values['selectorStatus'], ['OK', 'NEEDS_PRICE'], true)) throw new BadRequest('Invalid selectorStatus.');
        if (!in_array($values['channel'] ?? 'internal', ['internal', 'email', 'other'], true)) throw new BadRequest('Invalid channel.');
        if ($this->acl->getLevel('NgShortlistSnapshot', 'create') === Table::LEVEL_NO) throw new Forbidden();
        // Uniform deny shape: unknown and foreign-existing parents are indistinguishable.
        $shortlist = $this->entityManager->getEntityById('NgShortlist', (string) $values['shortlistId']);
        if (!$shortlist || !$this->acl->checkEntityRead($shortlist)) throw new NotFoundSilent();
        $quote = $this->entityManager->getEntityById('NgSourceQuote', (string) $values['quoteId']);
        if (!$quote || !$this->acl->checkEntityRead($quote)) throw new NotFoundSilent();

        return $this->createWithRevision($values);
    }

    /** Derives the next revision under an exclusive transaction; never trusts client revision input. */
    private function createWithRevision(array $values): Response
    {
        $entity = null;

        // Five concurrent writers can queue behind the same shortlist row;
        // allow enough restart attempts for every waiter to observe the new head.
        for ($attempt = 0; $attempt < 6 && $entity === null; $attempt++) {
            try {
                $this->entityManager->getTransactionManager()->run(function () use (&$entity, $values) {
            $shortlist = $this->entityManager->getEntityById('NgShortlist', (string) $values['shortlistId']);

            if (!$shortlist) {
                throw new NotFoundSilent();
            }

            // Serialize concurrent snapshot creators on the owning shortlist row
            // (mirrors QuoteCreator's forUpdate pattern) so revisionNumber max+1
            // cannot race into a unique violation or mint duplicate revisions.
            $this->entityManager
                ->getRDBRepository('NgShortlist')
                ->forUpdate()
                ->where(['id' => $values['shortlistId']])
                ->findOne();

            // FX4: the unique index (shortlistId, quoteId, revisionNumber)
            // retains soft-deleted slots. Allocate above the max across ALL
            // rows, including deleted ones, or a deleted head causes the same
            // number to be assigned again and every retry hits 1062.
            $max = $this->entityManager
                ->getRDBRepository('NgShortlistSnapshot')
                ->where([
                    'shortlistId' => $values['shortlistId'],
                    'quoteId' => $values['quoteId'],
                    'deleted' => [0, 1],
                ])
                ->max('revisionNumber');

            // The entity schema has no predecessorId column. Still resolve the
            // contract's live head by revision order (rather than treating the
            // deleted max as a predecessor); if a future schema exposes the
            // optional field, bind it to that non-deleted head without adding
            // a migration or changing this two-file FX4 scope.
            $head = $this->entityManager
                ->getRDBRepository('NgShortlistSnapshot')
                ->where([
                    'shortlistId' => $values['shortlistId'],
                    'quoteId' => $values['quoteId'],
                    'deleted' => false,
                ])
                ->order('revisionNumber', 'DESC')
                ->findOne();

            $next = $max === null ? 1 : (int) $max + 1;

            $entity = $this->entityManager->getNewEntity('NgShortlistSnapshot');
            if ($head && $entity->has('predecessorId')) {
                $entity->set('predecessorId', $head->getId());
            }
            $entity->set($values);
            $entity->set('revisionNumber', $next);
            $entity->set('capturedAt', gmdate('Y-m-d H:i:s'));
            $entity->set('capturedBy', (string) $this->user->getId());
            $entity->set('publicEligibility', false);
            // Snapshot ownership follows the shortlist owner so that own-level
            // ACL (read=own) on the snapshot matches the parent shortlist.
            $ownerId = $shortlist->get('assignedUserId');
            if ($ownerId) {
                $entity->set('assignedUserId', (string) $ownerId);
            }
            $this->entityManager->saveEntity($entity, ['silent' => true, 'noStream' => true, 'noNotifications' => true]);
                });
            } catch (\PDOException $e) {
                // FX4: retry on every serialization outcome of the head race,
                // not only the duplicate-key error. Under concurrent creators
                // MariaDB also raises 1020 ("Record has changed since last
                // read ... try restarting transaction") and, rarely, 1213
                // (deadlock) / 1205 (lock wait timeout). Those are retryable
                // by definition; treating them as fatal turned a lost race
                // into a 500.
                $message = $e->getMessage();
                $isRetryable = str_contains($message, 'UNIQ_SHORTLIST_QUOTE_REVISION')
                    || str_contains($message, 'shortlistQuoteRevision')
                    || str_contains($message, 'Record has changed since last read')
                    || str_contains($message, 'SQLSTATE[HY000]: General error: 1020')
                    || str_contains($message, 'Deadlock found')
                    || str_contains($message, 'Lock wait timeout');

                if (!$isRetryable || $attempt === 5) {
                    throw $e;
                }
                // A concurrent writer won the head race; retry the transaction
                // after its commit so the next revision is recomputed.
                usleep(50000 * ($attempt + 1));
                $entity = null;
            }
        }

        return ResponseComposer::json([
            'id' => (string) $entity->getId(),
            'selectorStatus' => (string) $entity->get('selectorStatus'),
            'revisionNumber' => (int) $entity->get('revisionNumber'),
            'publicEligibility' => (bool) $entity->get('publicEligibility'),
        ]);
    }
}
