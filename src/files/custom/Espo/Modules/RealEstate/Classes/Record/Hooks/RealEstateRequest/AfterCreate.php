<?php
/**LICENSE**/

namespace Espo\Modules\RealEstate\Classes\Record\Hooks\RealEstateRequest;

use Espo\Core\Record\Hook\SaveHook;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\ORM\Entity;

/**
 * @implements SaveHook<RealEstateRequest>
 */
class AfterCreate implements SaveHook
{
    public function process(Entity $entity): void
    {
        if (!$entity->get('name')) {
            $entity->set('name', $entity->get('number'));
        }
    }
}