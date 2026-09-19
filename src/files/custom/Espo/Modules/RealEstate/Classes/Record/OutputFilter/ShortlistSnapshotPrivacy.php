<?php
namespace Espo\Modules\RealEstate\Classes\Record\OutputFilter;

use Espo\Core\Record\Output\Filter;
use Espo\ORM\Entity;

/**
 * Customer-safe sent snapshot projection. The source quote is an internal
 * creation-time input; source/provider/terms/evidence fields never cross the
 * NgShortlistSnapshot API boundary. The approved public field is quoteId.
 */
class ShortlistSnapshotPrivacy implements Filter
{
    private const INTERNAL_FIELDS = [
        'sourceQuoteId',
        'sourceQuoteName',
        'sourceProviderId',
        'sourceProviderType',
        'sourceSnapshot',
        'terms',
        'evidenceRefs',
        'sourceEvidenceRefs',
        'notes',
        'commissionAmount',
        'auctionAmount',
    ];

    public function filter(Entity $entity): void
    {
        foreach (self::INTERNAL_FIELDS as $field) {
            if ($entity->has($field)) {
                $entity->clear($field);
            }
        }
    }
}