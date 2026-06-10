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

namespace Espo\Modules\RealEstate\Hooks\RealEstateProperty;

use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;

class SetValues
{
    public function __construct(private Metadata $metadata)
    {}

    public function beforeSave(Entity $entity): void
    {
        $propertyType = $entity->get('type');

        $fieldList = $this->metadata
            ->get(['entityDefs', 'RealEstateProperty', 'propertyTypes', $propertyType, 'fieldList'], []);

        $fieldDefs = $this->metadata->get(['entityDefs', 'RealEstateProperty', 'fields'], []);

        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) {
                continue;
            }

            if (!in_array($field, $fieldList)) {
                $entity->set($field, null);
            }
        }

        $name = '';

        if ($entity->get('addressStreet') || $entity->get('addressCity')) {
            if ($entity->get('addressStreet')) {
                $name .= str_replace("\n", ', ', $entity->get('addressStreet'));
            }

            if ($entity->get('addressCity')) {
                if ($name != '') {
                    $name .= ", ";
                }

                $name .= $entity->get('addressCity');
            }
        }
        else {
            $name = "unknown-address";
        }

        $entity->set('name', $name);
    }
}
