<?php
namespace Espo\Modules\RealEstate\Hooks\NgShortlist;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

/**
 * Server-side lifecycle guard for NgShortlist.
 * - inquiryId/propertyId/offerCycleId immutable after create.
 * - status follows a strict transition matrix.
 * - `not-fit`/removal requires removeReason.
 * - never mutates RealEstateRequest status, RealEstateProperty price/status,
 *   and never creates Deals/Opportunities/reservations.
 */
class Lifecycle
{
    public static $order = 10;

    private const TRANSITIONS = [
        'suggested' => ['sent', 'not-fit'],
        'sent' => ['interested', 'viewed', 'not-fit'],
        'interested' => ['sent', 'viewed', 'selected', 'not-fit'],
        'viewed' => ['interested', 'selected', 'not-fit'],
        'selected' => ['not-fit'],
        'not-fit' => [],
    ];

    private const TERMINAL = ['not-fit'];

    public function beforeSave(Entity $entity, array $options): void
    {
        if ($entity->isNew()) {
            $this->assertInitial($entity);
            return;
        }

        foreach (['addedAt', 'addedBy', 'sentAt', 'sentBy', 'removedAt', 'removedBy', 'removeReason'] as $field) {
            if ($entity->has($field) && $entity->get($field) != $entity->getFetched($field)) {
                $changed = $entity->get($field) !== null && $entity->getFetched($field) !== null;
                if ($changed || $entity->getFetched($field) !== null) {
                    throw new BadRequest("NgShortlist.{$field} is immutable event fact.");
                }
            }
        }

        foreach (['inquiryId', 'propertyId', 'offerCycleId'] as $field) {
            if ($entity->has($field) && $entity->get($field) != $entity->getFetched($field)) {
                throw new BadRequest("NgShortlist.{$field} is immutable after create.");
            }
        }

        $this->assertTransition($entity);
        $this->assertRemoveReason($entity);
    }

    private function assertInitial(Entity $entity): void
    {
        if ((string) $entity->get('status') !== 'suggested') {
            throw new BadRequest('NgShortlist must be created as suggested.');
        }
        if (!$entity->get('inquiryId') || !$entity->get('propertyId')) {
            throw new BadRequest('NgShortlist requires inquiryId and propertyId.');
        }
    }

    private function assertTransition(Entity $entity): void
    {
        $from = (string) $entity->getFetched('status');
        $to = (string) $entity->get('status');
        if ($from === '' || $from === $to) {
            return;
        }
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new BadRequest("NgShortlist invalid transition {$from} -> {$to}.");
        }
    }

    private function assertRemoveReason(Entity $entity): void
    {
        $to = (string) $entity->get('status');
        if (!in_array($to, self::TERMINAL, true)) {
            return;
        }
        $hasReason = (string) $entity->get('removeReason') !== '';
        if (!$hasReason && $to === (string) $entity->getFetched('status')) {
            return;
        }
        if (!$hasReason) {
            throw new BadRequest('NgShortlist requires removeReason when not-fit.');
        }
    }
}
