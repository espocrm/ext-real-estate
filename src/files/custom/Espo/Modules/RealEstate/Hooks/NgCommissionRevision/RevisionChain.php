<?php
namespace Espo\Modules\RealEstate\Hooks\NgCommissionRevision;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
class RevisionChain
{
    public function __construct(private EntityManager $entityManager) {}
    public function beforeSave(Entity $e,array $options): void
    {
        if (!$e->isNew()) { foreach(['method','percentage','fixedAmount','baseAmount','currency','payer','offerCycleId','sourceQuoteId'] as $f) if($e->has($f)&&$e->get($f)!==$e->getFetched($f)) throw new BadRequest("NgCommissionRevision.{$f} is immutable; create correction revision."); return; }
        if (!$e->get('offerCycleId')&&!$e->get('sourceQuoteId')) throw new BadRequest('Commission revision requires OfferCycle or SourceQuote.');
        $method=(string)$e->get('method');
        if($method==='percentage'&&(!$e->get('percentage')||!$e->get('baseAmount'))) throw new BadRequest('Percentage commission requires percentage and baseAmount.');
        if($method==='fixed'&&!$e->get('fixedAmount')) throw new BadRequest('Fixed commission requires fixedAmount.');
        $parent=['offerCycleId'=>$e->get('offerCycleId'),'sourceQuoteId'=>$e->get('sourceQuoteId')];$max=$this->entityManager->getRDBRepository('NgCommissionRevision')->where($parent)->max('revisionNumber');$e->set('revisionNumber',$max===null?1:(int)$max+1);if($max!==null){$head=$this->entityManager->getRDBRepository('NgCommissionRevision')->where($parent+['revisionNumber'=>(int)$max])->findOne();if($head)$e->set('predecessorId',$head->getId());}$e->set('publicEligibility',false);
    }
}
