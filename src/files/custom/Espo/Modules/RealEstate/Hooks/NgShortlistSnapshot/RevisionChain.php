<?php
namespace Espo\Modules\RealEstate\Hooks\NgShortlistSnapshot;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

/**
 * Immutable sent-fact snapshot (FC-SL-007/008):
 * - quote facts immutable after create (no rewrite by later revisions);
 * - publicEligibility always forced false;
 * - capturedAt/capturedBy set at creation when absent.
 */
class RevisionChain
{
    public function beforeSave(Entity $entity, array $options): void
    {
        if (!$entity->isNew()) {
            foreach (['shortlistId', 'quoteId', 'revisionNumber', 'amount', 'currency', 'unit', 'scenario', 'asOf', 'ruleVersion', 'selectorStatus', 'channel', 'capturedAt', 'capturedBy', 'publicEligibility'] as $field) {
                if ($entity->has($field) && $entity->get($field) !== $entity->getFetched($field)) {
                    throw new BadRequest("NgShortlistSnapshot.{$field} is immutable; create a new snapshot.");
                }
            }
            return;
        }

        $status = (string) $entity->get('selectorStatus');
        if (!in_array($status, ['OK', 'NEEDS_PRICE'], true)) {
            throw new BadRequest('NgShortlistSnapshot requires selectorStatus OK or NEEDS_PRICE.');
        }
        if ($status === 'OK') {
            if (!$entity->get('amount') || !$entity->get('currency')) {
                throw new BadRequest('OK snapshot requires amount and currency.');
            }
        } else {
            $entity->set('amount', null);
        }

        $entity->set('publicEligibility', false);
        if (!$entity->get('capturedAt')) {
            $entity->set('capturedAt', gmdate('Y-m-d H:i:s'));
        }
        if (!$entity->get('capturedBy')) {
            $entity->set('capturedBy', (string) ($entity->get('capturedBy') ?: 'system'));
        }
    }
}
