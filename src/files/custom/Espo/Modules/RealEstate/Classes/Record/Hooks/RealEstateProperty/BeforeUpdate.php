<?php
/************************************************************************
* This file is part of EspoCRM.
*
* EspoCRM – Open Source CRM application.
* Copyright (C) 2014-2026 EspoCRM, Inc.
* Website: https://www.espocrm.com
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program. If not, see <https://www.gnu.org/licenses/>.
*
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU Affero General Public License version 3.
*
* In accordance with Section 7(b) of the GNU Affero General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
************************************************************************/

namespace Espo\Modules\RealEstate\Classes\Record\Hooks\RealEstateProperty;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Core\Select\SearchParams;
use Espo\Modules\RealEstate\Entities\RealEstateProperty;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\Modules\RealEstate\Tools\Property\Service;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 * @implements UpdateHook<RealEstateProperty>
 */
class BeforeUpdate implements UpdateHook
{
    public function __construct(
        private Service $service,
        private EntityManager $entityManager
    ) {}

    /**
     * @param RealEstateProperty $entity
     * @throws BadRequest
     * @throws Forbidden
     */
    public function process(Entity $entity, UpdateParams $params): void
    {
        $matchingRequestCount = null;

        if ($entity->isActual()) {
            $query = $this->service->getMatchingRequestsQuery($entity, SearchParams::create());

            $matchingRequestCount = $this->entityManager
                ->getRDBRepository(RealEstateRequest::ENTITY_TYPE)
                ->clone($query)
                ->count();
        }

        $entity->set('matchingRequestCount', $matchingRequestCount);
    }
}
