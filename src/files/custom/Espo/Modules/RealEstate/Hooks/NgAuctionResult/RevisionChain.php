<?php
namespace Espo\Modules\RealEstate\Hooks\NgAuctionResult;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
class RevisionChain
{
    public function __construct(private EntityManager $entityManager) {}
    public function beforeSave(Entity $entity, array $options): void
    {
        if (!$entity->isNew()) {
            foreach (['lotId','resultState','winningAmount','currency','priceUnit'] as $f) {
                if ($entity->has($f) && $entity->get($f)!==$entity->getFetched($f)) throw new BadRequest("AuctionResult.{$f} is immutable; append correction revision.");
            }
            return;
        }
        $lotId=$entity->get('lotId');if(!$lotId)throw new BadRequest('AuctionResult requires lotId.');
        $state=(string)$entity->get('resultState');
        if (!in_array($state, ['no-result','won','cancelled','correction'], true)) {
            throw new BadRequest('Invalid AuctionResult resultState.');
        }
        if ($state === 'won' && (!$entity->get('winningAmount') || !$entity->get('currency') || !$entity->get('priceUnit'))) {
            throw new BadRequest('Won result requires winningAmount, currency and priceUnit.');
        }
        if ($state === 'no-result' && $entity->get('winningAmount') !== null && $entity->get('winningAmount') !== '') {
            throw new BadRequest('No-result cannot contain winningAmount.');
        }
        $max=$this->entityManager->getRDBRepository('NgAuctionResult')->where(['lotId'=>$lotId])->max('revisionNumber');
        $entity->set('revisionNumber',$max===null?1:(int)$max+1);
        if($max!==null){$head=$this->entityManager->getRDBRepository('NgAuctionResult')->where(['lotId'=>$lotId,'revisionNumber'=>(int)$max])->findOne();if($head)$entity->set('predecessorId',$head->getId());}
    }
}
