<?php
namespace Espo\Modules\RealEstate\Classes\Record\OutputFilter;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Record\Output\Filter;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * H01 — NgAuctionResult restricted-field projection.
 *
 * Sales/own-level actors never receive auction result price facts or source
 * provenance through the generic read/list/search/relation surface. Only a
 * full-level (or admin) actor sees them.
 */
class AuctionResultPrivacy implements Filter
{
    private const RESTRICTED = [
        'winningAmount',
        'currency',
        'priceUnit',
        'sourceEvidenceRefs',
        'sourceAsOf',
        'notes',
        // Request identity is not a business fact and must not leak: knowing a
        // key would let another actor attempt a replay guess.
        'requestId',
    ];

    public function __construct(private Acl $acl, private User $user) {}

    public function filter(Entity $entity): void
    {
        if ($this->user->isAdmin()) return;

        if ($this->acl->getLevel($entity->getEntityType(), 'read') !== Table::LEVEL_ALL) {
            foreach (self::RESTRICTED as $field) {
                $entity->clear($field);
            }
        }
    }
}
