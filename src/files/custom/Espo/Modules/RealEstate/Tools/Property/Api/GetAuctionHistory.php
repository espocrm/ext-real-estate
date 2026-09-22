<?php
namespace Espo\Modules\RealEstate\Tools\Property\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Read-only historical auction projection for Property detail. */
class GetAuctionHistory implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user
    ) {}

    public function process(Request $request): Response
    {
        $propertyId = (string) $request->getRouteParam('id');
        if ($propertyId === '') {
            throw new NotFound();
        }

        $property = $this->entityManager->getEntityById('RealEstateProperty', $propertyId);
        if (!$property) {
            throw new NotFound();
        }
        if (!$this->acl->checkEntityRead($property)) {
            throw new Forbidden();
        }

        // Admin receives the full approved historical projection. Other actors
        // never receive auction price facts through this facade.
        $full = $this->user->isAdmin()
            || $this->acl->getLevel('NgAuctionLot', 'read') === Table::LEVEL_ALL;

        $lotRepo = $this->entityManager->getRDBRepository('NgAuctionLot');
        $sessionRepo = $this->entityManager->getRDBRepository('NgAuctionSession');
        $resultRepo = $this->entityManager->getRDBRepository('NgAuctionResult');
        $lots = $lotRepo->where(['propertyId' => $propertyId, 'deleted' => false])->order('id')->find();

        $groups = [];
        foreach ($lots as $lot) {
            $sessionId = (string) $lot->get('sessionId');
            if (!isset($groups[$sessionId])) {
                $session = $sessionRepo->getById($sessionId);
                if (!$session) {
                    continue;
                }
                $groups[$sessionId] = [
                    'session' => [
                        'id' => (string) $session->getId(),
                        'code' => (string) $session->get('code'),
                        'name' => (string) $session->get('name'),
                        'scheduledAt' => $session->get('scheduledAt'),
                        'status' => (string) $session->get('status'),
                        'method' => (string) $session->get('method'),
                    ],
                    'lots' => [],
                ];
            }

            // H01: source provenance fields (sourceAsOf/sourceEvidenceRefs) are
            // restricted the same as price facts; only the full projection exposes them.
            $item = [
                'id' => (string) $lot->getId(),
                'lotCode' => (string) $lot->get('lotCode'),
                'resultState' => (string) $lot->get('resultState'),
            ];
            if ($full) {
                $item['currency'] = (string) $lot->get('currency');
                $item['priceUnit'] = (string) $lot->get('priceUnit');
                $item['sourceAsOf'] = $lot->get('sourceAsOf');
                $item['startingAmount'] = $lot->get('startingAmount');
                $item['depositAmount'] = $lot->get('depositAmount');
                $item['stepAmount'] = $lot->get('stepAmount');
                $item['winningAmount'] = $lot->get('winningAmount');
            }

            $result = $resultRepo
                ->where(['lotId' => (string) $lot->getId(), 'deleted' => false])
                ->order('revisionNumber', 'DESC')
                ->findOne();
            if ($result) {
                $resultData = [
                    'id' => (string) $result->getId(),
                    'resultState' => (string) $result->get('resultState'),
                    'revisionNumber' => (int) $result->get('revisionNumber'),
                ];
                if ($full) {
                    $resultData['sourceAsOf'] = $result->get('sourceAsOf');
                    $resultData['winningAmount'] = $result->get('winningAmount');
                    $resultData['currency'] = $result->get('currency');
                    $resultData['priceUnit'] = $result->get('priceUnit');
                }
                $item['latestResult'] = $resultData;
            }
            $groups[$sessionId]['lots'][] = $item;
        }

        return ResponseComposer::json([
            'propertyId' => $propertyId,
            'projection' => $full ? 'full-historical' : 'historical-no-price-facts',
            'history' => array_values($groups),
        ]);
    }
}
