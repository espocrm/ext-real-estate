<?php
namespace Espo\Modules\RealEstate\Tools\Shortlist\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/** Bounded Shortlist create action; generic CRUD create stays denied. */
class Create implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user
    ) {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody();
        if (!$data || !is_object($data)) throw new BadRequest('Missing shortlist payload.');
        $input = get_object_vars($data);
        $allowed = ['inquiryId', 'propertyId', 'offerCycleId', 'fitReason', 'mismatchReason', 'channel'];
        $values = array_intersect_key($input, array_flip($allowed));
        foreach (['inquiryId', 'propertyId'] as $field) {
            if (empty($values[$field])) throw new BadRequest("{$field} is required.");
        }
        if (array_key_exists('channel', $values) && !in_array($values['channel'], ['internal', 'email', 'other'], true)) {
            throw new BadRequest('Invalid channel.');
        }
        if ($this->acl->getLevel('NgShortlist', 'create') === Table::LEVEL_NO) throw new Forbidden();
        // Uniform deny shape: unknown and foreign-existing parents are indistinguishable.
        foreach ([['RealEstateRequest', $values['inquiryId']], ['RealEstateProperty', $values['propertyId']]] as [$type, $id]) {
            $parent = $this->entityManager->getEntityById($type, (string) $id);
            if (!$parent || !$this->acl->checkEntityRead($parent)) throw new NotFoundSilent();
        }
        // Idempotent grain: inquiry × property. A repeated bounded create
        // returns the existing readable row instead of surfacing a DB 500.
        $existing = $this->entityManager
            ->getRDBRepository('NgShortlist')
            ->where([
                'inquiryId' => (string) $values['inquiryId'],
                'propertyId' => (string) $values['propertyId'],
                'deleted' => false,
            ])
            ->findOne();

        if ($existing) {
            if (!$this->acl->checkEntityRead($existing)) {
                throw new NotFoundSilent();
            }

            return ResponseComposer::json([
                'id' => (string) $existing->getId(),
                'status' => (string) $existing->get('status'),
                'inquiryId' => (string) $existing->get('inquiryId'),
                'propertyId' => (string) $existing->get('propertyId'),
                'idempotent' => true,
            ]);
        }

        $entity = $this->entityManager->getNewEntity('NgShortlist');
        $entity->set($values);
        $entity->set('addedAt', gmdate('Y-m-d H:i:s'));
        $entity->set('addedBy', (string) $this->user->getId());
        $entity->set('assignedUserId', (string) $this->user->getId());
        $this->entityManager->saveEntity($entity, ['silent' => true, 'noStream' => true, 'noNotifications' => true]);
        return ResponseComposer::json([
            'id' => (string) $entity->getId(),
            'status' => (string) $entity->get('status'),
            'inquiryId' => (string) $entity->get('inquiryId'),
            'propertyId' => (string) $entity->get('propertyId'),
        ]);
    }
}
