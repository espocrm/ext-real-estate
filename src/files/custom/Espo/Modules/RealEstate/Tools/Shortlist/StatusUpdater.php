<?php
namespace Espo\Modules\RealEstate\Tools\Shortlist;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Bounded Shortlist status transition (FC-SL-004/006/008).
 *
 * Accepts only status + removeReason + channel; records sent/removal event facts
 * server-side; leaves Inquiry/Property/Deal state untouched. Enforces the same
 * transition matrix as the Lifecycle hook and requires a remove reason when the
 * row moves to the terminal not-fit state.
 */
class StatusUpdater
{
    private const TRANSITIONS = [
        'suggested' => ['sent', 'not-fit'],
        'sent' => ['interested', 'viewed', 'not-fit'],
        'interested' => ['sent', 'viewed', 'selected', 'not-fit'],
        'viewed' => ['interested', 'selected', 'not-fit'],
        'selected' => ['not-fit'],
        'not-fit' => [],
    ];

    private const ALLOWED_FIELDS = ['status', 'removeReason', 'channel'];

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user
    ) {}

    public function update(string $id, object $data): array
    {
        $shortlist = $this->entityManager->getEntityById('NgShortlist', $id);

        if (!$shortlist) {
            throw new NotFound();
        }

        // Row-level edit check (own/all), not just scope-level edit != no.
        if (!$this->acl->checkEntityEdit($shortlist)) {
            throw new NotFoundSilent();
        }

        foreach (['inquiryId', 'propertyId'] as $field) {
            $parent = $this->entityManager->getEntityById(
                $field === 'inquiryId' ? 'RealEstateRequest' : 'RealEstateProperty',
                (string) $shortlist->get($field)
            );
            if (!$parent) {
                throw new NotFound();
            }
            if (!$this->acl->checkEntityRead($parent)) {
                throw new Forbidden();
            }
        }

        $values = [];
        foreach (self::ALLOWED_FIELDS as $field) {
            if (property_exists($data, $field)) {
                $values[$field] = $data->{$field};
            }
        }

        if (!array_key_exists('status', $values)) {
            throw new BadRequest('status is required.');
        }

        $to = (string) $values['status'];
        $from = (string) $shortlist->get('status');

        if ($from === $to) {
            throw new BadRequest('No status change.');
        }

        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new BadRequest("NgShortlist invalid transition {$from} -> {$to}.");
        }

        if ($to === 'not-fit' && empty($values['removeReason'])) {
            throw new BadRequest('NgShortlist requires removeReason when not-fit.');
        }

        // FC-SL-007: a sent snapshot must exist before a shortlist can move to `sent`.
        if ($to === 'sent') {
            $snapshot = $this->entityManager
                ->getRDBRepository('NgShortlistSnapshot')
                ->where(['shortlistId' => $id, 'deleted' => false])
                ->findOne();

            if (!$snapshot) {
                throw new BadRequest('NgShortlist requires a sent snapshot before status sent.');
            }
        }

        $shortlist->set('status', $to);

        if ($to === 'sent') {
            if (!$shortlist->get('sentAt')) {
                $shortlist->set('sentAt', gmdate('Y-m-d H:i:s'));
            }
            $shortlist->set('sentBy', (string) $this->user->getId());
        }

        if ($to === 'not-fit') {
            if (!$shortlist->get('removedAt')) {
                $shortlist->set('removedAt', gmdate('Y-m-d H:i:s'));
            }
            $shortlist->set('removedBy', (string) $this->user->getId());
        }

        if (array_key_exists('channel', $values) && $values['channel'] !== null && $values['channel'] !== '') {
            if (!in_array($values['channel'], ['internal', 'email', 'other'], true)) {
                throw new BadRequest('Invalid channel.');
            }
            $shortlist->set('channel', (string) $values['channel']);
        }
        if (array_key_exists('removeReason', $values) && $values['removeReason'] !== null) {
            $shortlist->set('removeReason', (string) $values['removeReason']);
        }

        $this->entityManager->saveEntity($shortlist, ['silent' => true, 'noStream' => true, 'noNotifications' => true]);

        return [
            'id' => (string) $shortlist->getId(),
            'status' => (string) $shortlist->get('status'),
            'sentAt' => $shortlist->get('sentAt'),
            'sentBy' => $shortlist->get('sentBy'),
            'removedAt' => $shortlist->get('removedAt'),
            'removedBy' => $shortlist->get('removedBy'),
        ];
    }
}