<?php
namespace Espo\Modules\RealEstate\Classes\Select\Shortlist;

use Espo\Core\Select\Applier\AdditionalApplier;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;

/**
 * List-time parent-read filter.
 *
 * NgShortlist / NgShortlistSnapshot are only listed when their parent records
 * (Inquiry + Property / Shortlist + SourceQuote) are readable by the current
 * user. The filter is expressed as `IN (subquery)` conditions where each
 * subquery applies the parent entity's own ACL via SelectBuilder with the
 * access-control filter enabled. A parent the user cannot read yields an empty
 * subquery (`id IS NULL`), hiding the row without leaking existence.
 */
class ParentReadFilter implements AdditionalApplier
{
    public function __construct(
        private string $entityType,
        private SelectBuilderFactory $selectBuilderFactory
    ) {}

    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        foreach ($this->getParentMap() as $foreignKey => $parentEntityType) {
            $subquery = $this->buildParentReadSubquery($parentEntityType);

            if ($subquery === null) {
                continue;
            }

            $queryBuilder->where([$foreignKey . '=s' => $subquery]);
        }
    }

    /**
     * @return array<string, string> field => parent entity type
     */
    private function getParentMap(): array
    {
        if ($this->entityType === 'NgShortlist') {
            return [
                'inquiryId' => 'RealEstateRequest',
                'propertyId' => 'RealEstateProperty',
            ];
        }

        if ($this->entityType === 'NgShortlistSnapshot') {
            return [
                'shortlistId' => 'NgShortlist',
                'quoteId' => 'NgSourceQuote',
            ];
        }

        return [];
    }

    private function buildParentReadSubquery(string $parentEntityType): ?Select
    {
        try {
            $query = $this->selectBuilderFactory
                ->create()
                ->from($parentEntityType)
                ->withAccessControlFilter()
                ->build();
        } catch (\Throwable) {
            // If the parent cannot be read at all, treat it as no rows.
            return Select::fromRaw([
                'select' => ['id'],
                'from' => $parentEntityType,
                'whereClause' => ['id' => null],
            ]);
        }

        $raw = $query->getRaw();

        return Select::fromRaw([
            'select' => ['id'],
            'from' => $parentEntityType,
            'whereClause' => $raw['whereClause'] ?? [],
            'joins' => $raw['joins'] ?? [],
            'leftJoins' => $raw['leftJoins'] ?? [],
        ]);
    }
}