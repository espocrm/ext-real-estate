<?php
namespace Espo\Modules\RealEstate\Tools\Commission;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PDOException;
use RuntimeException;
class CommissionRevisionCreator
{
    public function __construct(private EntityManager $em) {}
    public function create(array $v): Entity
    {
        if(empty($v['offerCycleId'])&&!empty($v['sourceQuoteId'])){}elseif(empty($v['offerCycleId'])&&empty($v['sourceQuoteId']))throw new BadRequest('Commission requires parent.');
        for($i=1;$i<=3;$i++){try{return $this->attempt($v);}catch(PDOException $e){if(!str_contains($e->getMessage(),'1062')&&!str_contains($e->getMessage(),'23000'))throw $e;usleep(50000*$i);}}
        throw new RuntimeException('Commission revision collision retry exhausted.');
    }
    private function attempt(array $v): Entity
    {
        $tx=$this->em->getTransactionManager();if($tx->isStarted())throw new RuntimeException('Outer transaction forbidden.');$out=null;
        $tx->run(function()use($v,&$out){$parent=$v['offerCycleId']??null;$parentType=$parent?'NgOfferCycle':'NgSourceQuote';$parentId=$parent?:$v['sourceQuoteId'];$this->em->getRDBRepository($parentType)->forUpdate()->where(['id'=>$parentId])->findOne();$where=['offerCycleId'=>$v['offerCycleId']??null,'sourceQuoteId'=>$v['sourceQuoteId']??null];$head=$this->em->getRDBRepository('NgCommissionRevision')->where($where)->order('revisionNumber','DESC')->findOne();$e=$this->em->getNewEntity('NgCommissionRevision');$e->set($v);$e->set('revisionNumber',$head?(int)$head->get('revisionNumber')+1:1);$e->set('publicEligibility',false);if($head)$e->set('predecessorId',$head->getId());$this->em->saveEntity($e,['silent'=>true,'noStream'=>true,'noNotifications'=>true]);$out=$e;});return $out;
    }
}
