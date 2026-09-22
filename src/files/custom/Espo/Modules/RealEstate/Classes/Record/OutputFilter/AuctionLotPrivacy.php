<?php
namespace Espo\Modules\RealEstate\Classes\Record\OutputFilter;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Record\Output\Filter;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * H01 — NgAuctionLot forbidden-field projection.
 *
 * Sales own/team cannot read start/deposit/step/winning/source fields through
 * any generic surface (list/read/relation/export/search/count). Admin/actor
 * with explicit permission can still access.
 */
class AuctionLotPrivacy implements Filter
{
    private const SENSITIVE = [
        'startingAmount',
        'depositAmount',
        'stepAmount',
        'winningAmount',
        'sourceEvidenceRefs',
        'sourceAsOf',
    ];

    public function __construct(private Acl $acl, private User $user) {}

    public function filter(Entity $entity): void
    {
        if ($this->user->isAdmin()) return;

        if ($this->acl->getLevel($entity->getEntityType(), 'read') !== Table::LEVEL_ALL) {
            foreach (self::SENSITIVE as $field) {
                $entity->clear($field);
            }
        }
    }
}
