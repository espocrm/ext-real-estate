<?php
/************************************************************************
 * This file is part of Real Estate extension for EspoCRM.
 *
 * Demo Data extension for EspoCRM.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * Demo Data extension is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Demo Data extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 ************************************************************************/

namespace Espo\Modules\RealEstate\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\NotFound;

use \Espo\ORM\Entity;

class RealEstateRequest extends \Espo\Core\Templates\Services\Base
{
    protected $readOnlyAttributeList = [
        'matchingPropertyCount'
    ];

    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        if (!$entity->get('name')) {
            $entity->set('name', $entity->get('number'));
        }
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        $matchingPropertyCount = null;
        if ($entity->isActual()) {
            $selectParams = $this->getMatchingPropertiesSelectParams($entity, []);
            $matchingPropertyCount = $this->getEntityManager()->getRepository('RealEstateProperty')->count($selectParams);
        }
        $entity->set('matchingPropertyCount', $matchingPropertyCount);
    }

    public function find($params)
    {
        if (!empty($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $i => $item) {
                if (!is_array($item)) continue;
                if (empty($item['attribute']) || $item['attribute'] !== 'matchingPropertyId') continue;
                unset($params['where'][$i]);
                if (empty($item['type']) || empty($item['value'])) continue;
                if ($item['type'] == 'equals') {
                    return $this->getServiceFactory()->create('RealEstateProperty')->findLinkedEntitiesMatchingRequests($item['value'], $params, true);
                }

            }
        }

        return parent::find($params);
    }

    public function findEntities($params) // TODO remove
    {
        if (!empty($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $i => $item) {
                if (!is_array($item)) continue;
                if (empty($item['attribute']) || $item['attribute'] !== 'matchingPropertyId') continue;
                unset($params['where'][$i]);
                if (empty($item['type']) || empty($item['value'])) continue;
                if ($item['type'] == 'equals') {
                    return $this->getServiceFactory()->create('RealEstateProperty')->findLinkedEntitiesMatchingRequests($item['value'], $params, true);
                }

            }
        }

        return parent::findEntities($params);
    }

    public function getMatchingPropertiesSelectParams($entity, $params)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $selectManager = $this->getSelectManager('RealEstateProperty');
        $selectParams = $selectManager->getSelectParams($params, true);

        $locationIdList = $entity->getLinkMultipleIdList('locations');
        if (!empty($locationIdList)) {
            $selectParams['customJoin'] .= " JOIN real_estate_location_path AS `realEstateLocationPath` ON realEstateLocationPath.descendor_id = real_estate_property.location_id ";
            $selectParams['whereClause']['realEstateLocationPath.ascendorId'] = $locationIdList;
            $selectParams['distinct'] = true;
        }

        if (empty($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }
        if (empty($selectParams['customJoin'])) {
            $selectParams['customJoin'] = '';
        }

        $selectParams['customWhere'] .= " AND real_estate_property.id NOT IN (SELECT property_id FROM opportunity WHERE request_id = ".$pdo->quote($entity->id)." AND deleted = 0)";

        $selectParams['customJoin'] .= "
            LEFT JOIN real_estate_property_real_estate_request AS propertiesMiddle
            ON
            propertiesMiddle.real_estate_property_id = real_estate_property.id AND
            propertiesMiddle.deleted = 0 AND
            propertiesMiddle.real_estate_request_id = ".$pdo->quote($entity->id)."
        ";
        $selectParams['additionalSelectColumns'] = array(
            'propertiesMiddle.interest_degree' => 'interestDegree'
        );

        $primaryFilter = null;
        switch ($entity->get('type')) {
            case 'Rent':
                $primaryFilter = 'actualRent';
                break;
            case 'Sale':
                $primaryFilter = 'actualSale';
                break;
        }
        if ($primaryFilter) {
            $selectManager->applyPrimaryFilter($primaryFilter, $selectParams);
        }

        if ($entity->get('propertyType')) {
            $selectParams['whereClause']['type'] = $entity->get('propertyType');
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields'], []);
        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) continue;

            if ($entity->get('from'. ucfirst($field)) !== null) {
                $selectParams['whereClause'][$field . '>='] = $entity->get('from' . ucfirst($field));
            }
            if ($entity->get('to'. ucfirst($field)) !== null) {
                $selectParams['whereClause'][$field . '<='] = $entity->get('to' . ucfirst($field));
            }
        }

        $defaultCurrency = $this->getConfig()->get('defaultCurrency');
        if ($entity->get('fromPrice') !== null) {
            $fromPrice = $entity->get('fromPrice');
            $fromPriceCurrency = $entity->get('fromPriceCurrency');

            $rates = $this->getConfig()->get('currencyRates');
            $rate1 = $this->getConfig()->get('currencyRates.' . $fromPriceCurrency, 1.0);

            $rate1 = 1.0;
            if (!empty($rates[$fromPriceCurrency])) {
                $rate1 = $rates[$fromPriceCurrency];
            }
            $rate2 = 1.0;
            if (!empty($rates[$defaultCurrency])) {
                $rate2 = $rates[$defaultCurrency];
            }
            $fromPrice = $fromPrice * ($rate1);
            $fromPrice = $fromPrice / ($rate2);

            $selectParams['whereClause']['priceConverted>='] = $fromPrice;
        }
        if ($entity->get('toPrice') !== null) {
            $toPrice = $entity->get('toPrice');
            $toPriceCurrency = $entity->get('toPriceCurrency');

            $rates = $this->getConfig()->get('currencyRates');
            $rate1 = $this->getConfig()->get('currencyRates.' . $toPriceCurrency, 1.0);

            $rate1 = 1.0;
            if (!empty($rates[$toPriceCurrency])) {
                $rate1 = $rates[$toPriceCurrency];
            }
            $rate2 = 1.0;
            if (!empty($rates[$defaultCurrency])) {
                $rate2 = $rates[$defaultCurrency];
            }
            $toPrice = $toPrice * ($rate1);
            $toPrice = $toPrice / ($rate2);

            $selectParams['whereClause']['priceConverted<='] = $toPrice;
        }

        return $selectParams;
    }

    public function findLinkedEntitiesMatchingProperties($id, $params, $customOrder = false)
    {
        $entity = $this->getRepository()->get($id);
        $this->loadAdditionalFields($entity);

        $selectParams = $this->getMatchingPropertiesSelectParams($entity, $params);

        if (!$customOrder) {
            $selectParams['orderBy'] = [
                ['propertiesMiddle.interest_degree'],
                ['LIST:status:New,Assigned,In Process'],
                ['createdAt', 'DESC']
            ];
        }

        $collection = $this->getEntityManager()->getRepository('RealEstateProperty')->find($selectParams);
        $recordService = $this->getRecordService('RealEstateProperty');

        foreach ($collection as $e) {
            $recordService->loadAdditionalFieldsForList($e);
            $recordService->prepareEntityForOutput($e);
        }

        $total = $this->getEntityManager()->getRepository('RealEstateProperty')->count($selectParams);

        if ($entity->isActual()) {
            $entity->set('matchingPropertyCount', $total);
            $this->getRepository()->save($entity, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true
            ]);
        }

        return array(
            'total' => $total,
            'collection' => $collection
        );
    }

    public function setNotIntereseted($requestId, $propertyId)
    {
        $request = $this->getEntity($requestId);
        if (!$request) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($request, 'edit')) {
            throw new Forbidden();
        }
        return $this->getEntityManager()->getRepository('RealEstateRequest')->relate($request, 'properties', $propertyId, array(
            'interestDegree' => 0
        ));
    }

    public function unsetNotIntereseted($requestId, $propertyId)
    {
        $request = $this->getEntity($requestId);
        if (!$request) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($request, 'edit')) {
            throw new Forbidden();
        }
        return $this->getEntityManager()->getRepository('RealEstateRequest')->unrelate($request, 'properties', $propertyId);
    }

    public function updateMatchingCount()
    {
        $repository = $this->getEntityManager()->getRepository('RealEstateRequest');

        $notActualList = $repository->select(['id', 'matchingPropertyCount'])->where([
            'status' => ['Completed', 'Canceled', 'Lost'],
            'matchingPropertyCount!=' => null
        ])->find();

        foreach ($notActualList as $e) {
            $e->set('matchingPropertyCount', null);
            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true
            ]);
        }

        $actualList = $repository->where([
            'status!=' => ['Completed', 'Canceled', 'Lost']
        ])->find();

        foreach ($actualList as $e) {
            $this->loadAdditionalFields($e);
            $selectParams = $this->getMatchingPropertiesSelectParams($e, []);
            $matchingPropertyCount = $this->getEntityManager()->getRepository('RealEstateProperty')->count($selectParams);
            $e->set('matchingPropertyCount', $matchingPropertyCount);
            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true
            ]);
        }
    }
}
