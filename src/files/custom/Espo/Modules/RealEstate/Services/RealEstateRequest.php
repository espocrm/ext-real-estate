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

namespace Espo\Modules\RealEstate\Services;

use Espo\Modules\RealEstate\Entities\RealEstateRequest as RequestEntity;
use Espo\Modules\RealEstate\Tools\Property\Service;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Record\FindParams;
use Espo\Services\Record;


/**
 * @extends Record<RequestEntity>
 */
class RealEstateRequest extends Record
{
    /**
     * @inheritDoc
     */
    public function find(SearchParams $searchParams, ?FindParams $params = null): RecordCollection
    {
        $where = $searchParams->getWhere();

        $itemList = [];

        if ($where && $where->getType() === WhereItem::TYPE_AND) {
            $itemList = $where->getItemList();
        }

        foreach ($itemList as $i => $item) {
            if ($item->getAttribute() !== 'matchingPropertyId') {
                continue;
            }

            unset($itemList[$i]);

            $where = WhereItem::createBuilder()
                ->setAttribute(WhereItem::TYPE_AND)
                ->setItemList(array_values($itemList))
                ->build();

            $searchParams = $searchParams->withWhere($where);

            if (!$item->getValue() || $item->getType() !== 'equals') {
                continue;
            }

            return $this->injectableFactory
                ->create(Service::class)
                ->findLinkedMatchingRequests($item->getValue(), $searchParams, true);
        }

        return parent::find($searchParams, $params);
    }
}
