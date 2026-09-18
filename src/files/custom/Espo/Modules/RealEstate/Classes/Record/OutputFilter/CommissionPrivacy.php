<?php
namespace Espo\Modules\RealEstate\Classes\Record\OutputFilter;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Record\Output\Filter;
use Espo\Entities\User;
use Espo\ORM\Entity;
class CommissionPrivacy implements Filter
{
    private const SENS=['method','percentage','fixedAmount','baseAmount','baseSemantic','expectedAmount','currency','payer','conditions','effectiveFrom','effectiveTo','policyRevision','sourceEvidenceRefs','notes'];
    public function __construct(private Acl $acl, private User $user) {}
    public function filter(Entity $entity): void
    {
        if ($this->user->isAdmin()) return;
        if ($this->acl->getLevel($entity->getEntityType(),'read') !== Table::LEVEL_ALL) {
            foreach (self::SENS as $f) $entity->clear($f);
        }
    }
}
