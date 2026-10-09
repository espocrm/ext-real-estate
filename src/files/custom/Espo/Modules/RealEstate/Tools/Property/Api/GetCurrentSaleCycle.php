<?php
namespace Espo\Modules\RealEstate\Tools\Property\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;

/** Server-side current SALE-cycle resolver; client never selects an arbitrary list row. */
class GetCurrentSaleCycle implements Action
{
    public function __construct(private EntityManager $entityManager, private Acl $acl) {}

    public function process(Request $request): Response
    {
        $propertyId = (string) $request->getRouteParam('id');
        $property = $this->entityManager->getEntityById('RealEstateProperty', $propertyId);
        if (!$property) throw new NotFound();
        if (!$this->acl->checkEntityRead($property)) throw new Forbidden();

        $cycles = $this->entityManager->getRDBRepository('NgOfferCycle')
            ->where(['propertyId' => $propertyId, 'intent' => 'SALE', 'deleted' => false])
            ->find();
        $active = [];
        foreach ($cycles as $cycle) {
            if ((string) $cycle->get('status') === 'active') $active[] = $cycle;
        }
        usort($active, static function ($a, $b): int {
            $at = (string) ($a->get('effectiveAt') ?? '');
            $bt = (string) ($b->get('effectiveAt') ?? '');
            if ($at !== $bt) return $bt <=> $at;
            return strcmp((string) $a->getId(), (string) $b->getId());
        });
        $chosen = $active[0] ?? null;
        return ResponseComposer::json([
            'propertyId' => $propertyId,
            'cycleId' => $chosen ? (string) $chosen->getId() : null,
            'status' => $chosen ? 'active' : null,
            'effectiveAt' => $chosen ? $chosen->get('effectiveAt') : null,
            'validUntil' => $chosen ? $chosen->get('validUntil') : null,
        ]);
    }
}
