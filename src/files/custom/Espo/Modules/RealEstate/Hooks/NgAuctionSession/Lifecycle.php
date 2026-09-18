<?php
namespace Espo\Modules\RealEstate\Hooks\NgAuctionSession;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
class Lifecycle
{
    private const MAP = [
        'pending'=>['scheduled','cancelled'],
        'scheduled'=>['held','cancelled'],
        'held'=>['completed','cancelled','result-cancelled'],
        'completed'=>['result-cancelled'],
        'cancelled'=>[],
        'result-cancelled'=>[],
    ];
    public function beforeSave(Entity $entity, array $options): void
    {
        if ($entity->isNew()) {
            if ((string)$entity->get('status') !== 'pending') throw new BadRequest('AuctionSession must start pending.');
            return;
        }
        $from=(string)$entity->getFetched('status');$to=(string)$entity->get('status');
        if ($from===$to) return;
        if (!in_array($to,self::MAP[$from]??[],true)) throw new BadRequest("Invalid AuctionSession transition {$from} -> {$to}.");
    }
}
