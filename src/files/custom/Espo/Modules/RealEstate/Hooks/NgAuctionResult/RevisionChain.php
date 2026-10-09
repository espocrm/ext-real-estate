<?php
namespace Espo\Modules\RealEstate\Hooks\NgAuctionResult;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
class RevisionChain
{
    private const IMMUTABLE_ON_EDIT = [
        'lotId',
        'resultState',
        'winningAmount',
        'currency',
        'priceUnit',
        'assignedUserId',
        'teamsIds',
        'requestId',
    ];
    private const VALUES_CANNOT_CHANGE = ['No-result cannot contain winningAmount.', 'Won result requires winningAmount, currency and priceUnit.'];

    public function __construct(private EntityManager $entityManager) {}
    public function beforeSave(Entity $entity, array $options): void
    {
        if (!$entity->isNew()) {
            foreach (self::IMMUTABLE_ON_EDIT as $f) {
                if ($entity->has($f) && $entity->get($f) !== $entity->getFetched($f)) {
                    throw new BadRequest("AuctionResult.{$f} is immutable; append correction revision.");
                }
            }
            return;
        }
        $lotId = $entity->get('lotId');
        if (!$lotId) throw new BadRequest('AuctionResult requires lotId.');
        $state = (string) $entity->get('resultState');
        if (!in_array($state, ['no-result', 'won', 'cancelled', 'correction'], true)) {
            throw new BadRequest('Invalid AuctionResult resultState.');
        }
        if ($state === 'won') {
            $winning = $entity->get('winningAmount');
            if ($winning === null || $winning === '' || !$entity->get('currency') || !$entity->get('priceUnit')) {
                throw new BadRequest('Won result requires winningAmount, currency and priceUnit.');
            }
            if ((!is_string($winning) && !is_int($winning)) ||
                (is_string($winning) && !preg_match('/^(?:0|[1-9]\d{0,15})(?:\.\d{1,2})?$/', $winning))) {
                throw new BadRequest('winningAmount must be a fixed-scale decimal string or integer.');
            }
            if (!in_array($entity->get('currency'), ['VND', 'USD'], true) ||
                !in_array($entity->get('priceUnit'), ['total', 'per_m2'], true)) {
                throw new BadRequest('Invalid currency or priceUnit.');
            }
        }
        if ($state === 'no-result' && $entity->get('winningAmount') !== null && $entity->get('winningAmount') !== '') {
            throw new BadRequest('No-result cannot contain winningAmount.');
        }
        // H05: the unique index (lotId, revisionNumber) keeps the slot of a
        // soft-deleted revision, so the allocator must consider deleted rows
        // too. Excluding them re-assigns a taken number and the insert then
        // fails with an unresolvable duplicate-key error.
        $max = $this->entityManager->getRDBRepository('NgAuctionResult')
            ->where(['lotId' => $lotId, 'deleted' => [0, 1]])
            ->max('revisionNumber');
        $entity->set('revisionNumber', $max === null ? 1 : (int) $max + 1);
        if ($max !== null) {
            $head = $this->entityManager->getRDBRepository('NgAuctionResult')
                ->where(['lotId' => $lotId, 'revisionNumber' => (int) $max, 'deleted' => false])
                ->findOne();
            if ($head) $entity->set('predecessorId', $head->getId());
        }
        $entity->set('publicEligibility', false);
    }
}