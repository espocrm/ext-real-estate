<?php
/*****************************************************
 * NGHIALAND DISPOSABLE — Slice A T1 (explicit-safe)
 * CriteriaValidation hook (NEW, additive)
 *
 * OD-05: reject invalid from/to criteria BEFORE native
 * SetValues::beforeSave can silently null them.
 * Does NOT modify upstream SetValues.php.
 ****************************************************/

namespace Espo\Modules\RealEstate\Hooks\RealEstateRequest;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;

class CriteriaValidation
{
    /** @var array<string,array{0:string,1:string}> to,base */
    private const PAIRS = [
        'fromSquare' => ['toSquare', 'square'],
        'fromPrice' => ['toPrice', 'price'],
        'fromBedroomCount' => ['toBedroomCount', 'bedroomCount'],
        'fromBathroomCount' => ['toBathroomCount', 'bathroomCount'],
        'fromFloor' => ['toFloor', 'floor'],
        'fromFloorCount' => ['toFloorCount', 'floorCount'],
        'fromYearBuilt' => ['toYearBuilt', 'yearBuilt'],
    ];

    /** Base fields that native SetValues may silently null (isMatching). */
    private const NATIVE_NULLED_BASES = [
        'square', 'bedroomCount', 'bathroomCount', 'floor', 'floorCount', 'yearBuilt',
    ];

    public function __construct(private Metadata $metadata)
    {}

    public function beforeSave(Entity $entity): void
    {
        if (!$entity->isNew() && !$this->hasAnyFromToChanged($entity)) {
            return;
        }

        $propertyType = $entity->get('propertyType');
        if (!$propertyType) {
            return;
        }

        $fieldList = $this->metadata
            ->get(['entityDefs', 'RealEstateProperty', 'propertyTypes', $propertyType, 'fieldList'], []);

        foreach (self::PAIRS as $from => [$to, $base]) {
            $fv = $entity->get($from);
            $tv = $entity->get($to);

            if ($fv === null && $tv === null) {
                continue;
            }

            if ($fv !== null && $tv !== null && $fv > $tv) {
                throw new BadRequest(
                    "Range criteria invalid: {$from} ({$fv}) must not exceed {$to} ({$tv})."
                );
            }

            if (
                in_array($base, self::NATIVE_NULLED_BASES, true) &&
                !in_array($base, $fieldList, true) &&
                ($fv !== null || $tv !== null)
            ) {
                throw new BadRequest(
                    "Criteria not allowed for propertyType '{$propertyType}': {$from}/{$to} (base '{$base}') — " .
                    'native SetValues would silently null it.'
                );
            }
        }
    }

    private function hasAnyFromToChanged(Entity $entity): bool
    {
        foreach (array_keys(self::PAIRS) as $field) {
            if ($entity->has($field) && $entity->get($field) != $entity->getFetched($field)) {
                return true;
            }
        }

        return false;
    }
}
