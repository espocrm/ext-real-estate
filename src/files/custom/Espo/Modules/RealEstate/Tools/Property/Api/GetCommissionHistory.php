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

/** Read-only authorized commission projection for Property detail. */
class GetCommissionHistory implements Action
{
    private const SENSITIVE = [
        'method', 'percentage', 'fixedAmount', 'baseAmount', 'baseSemantic',
        'expectedAmount', 'currency', 'payer', 'conditions', 'effectiveFrom',
        'effectiveTo', 'policyRevision', 'sourceEvidenceRefs', 'notes',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user
    ) {}

    public function process(Request $request): Response
    {
        $propertyId = (string) $request->getRouteParam('id');
        $property = $this->entityManager->getEntityById('RealEstateProperty', $propertyId);
        if (!$property) {
            throw new NotFound();
        }
        if (!$this->acl->checkEntityRead($property)) {
            throw new Forbidden();
        }

        $level = $this->acl->getLevel('NgCommissionRevision', 'read');
        if (!$this->user->isAdmin() && $level === Table::LEVEL_NO) {
            throw new Forbidden();
        }
        $full = $this->user->isAdmin() || $level === Table::LEVEL_ALL;

        $cycleRepo = $this->entityManager->getRDBRepository('NgOfferCycle');
        $commissionRepo = $this->entityManager->getRDBRepository('NgCommissionRevision');
        $cycles = $cycleRepo->where([
            'propertyId' => $propertyId,
            'intent' => 'SALE',
            'deleted' => false,
        ])->order('id', 'DESC')->find();

        $items = [];
        foreach ($cycles as $cycle) {
            $cycleId = (string) $cycle->getId();
            $revisions = $commissionRepo->where([
                'offerCycleId' => $cycleId,
                'deleted' => false,
            ])->order('revisionNumber')->find();
            foreach ($revisions as $revision) {
                $item = [
                    'id' => (string) $revision->getId(),
                    'offerCycleId' => $cycleId,
                    'sourceQuoteId' => $revision->get('sourceQuoteId'),
                    'revisionNumber' => (int) $revision->get('revisionNumber'),
                    'predecessorId' => $revision->get('predecessorId'),
                    'publicEligibility' => (bool) $revision->get('publicEligibility'),
                ];
                if ($full) {
                    foreach (['method', 'percentage', 'fixedAmount', 'baseAmount', 'baseSemantic',
                        'expectedAmount', 'currency', 'payer', 'conditions', 'effectiveFrom',
                        'effectiveTo', 'policyRevision', 'sourceEvidenceRefs', 'notes'] as $field) {
                        $item[$field] = $revision->get($field);
                    }
                }
                $items[] = $item;
            }
        }

        return ResponseComposer::json([
            'propertyId' => $propertyId,
            'projection' => $full ? 'full-authorized' : 'restricted-authorized',
            'revisions' => $items,
        ]);
    }
}
