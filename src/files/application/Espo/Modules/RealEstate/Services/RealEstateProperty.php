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

class RealEstateProperty extends \Espo\Core\Templates\Services\Base
{
    protected $readOnlyAttributeList = [
        'matchingRequestCount'
    ];

    public function find($params)
    {
        if (!empty($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $i => $item) {
                if (!is_array($item)) continue;
                if (empty($item['attribute']) || $item['attribute'] !== 'matchingRequestId') continue;
                unset($params['where'][$i]);
                if (empty($item['type']) || empty($item['value'])) continue;
                if ($item['type'] == 'equals') {
                    return $this->getServiceFactory()->create('RealEstateRequest')->findLinkedEntitiesMatchingProperties($item['value'], $params, true);
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
                if (empty($item['attribute']) || $item['attribute'] !== 'matchingRequestId') continue;
                unset($params['where'][$i]);
                if (empty($item['type']) || empty($item['value'])) continue;
                if ($item['type'] == 'equals') {
                    return $this->getServiceFactory()->create('RealEstateRequest')->findLinkedEntitiesMatchingProperties($item['value'], $params, true);
                }
            }
        }

        return parent::findEntities($params);
    }

    public function getMatchingRequestsSelectParams($entity, $params)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $selectManager = $this->getSelectManager('RealEstateRequest');
        $selectParams = $selectManager->getSelectParams($params, true);

        $locationId = $entity->get('locationId');
        if ($locationId) {
            $selectParams['leftJoins'][] = 'locations';
            $selectParams['customJoin'] .= "
                LEFT JOIN real_estate_location_path AS `realEstateLocationPathLeft` ON realEstateLocationPathLeft.descendor_id = locationsMiddle.real_estate_location_id
                LEFT JOIN real_estate_location_path AS `realEstateLocationPathRight` ON realEstateLocationPathRight.ascendor_id = locationsMiddle.real_estate_location_id
            ";
            $selectParams['whereClause'][] = [
                'OR' => [
                    ['realEstateLocationPathRight.descendorId' => $locationId],
                    ['realEstateLocationPathLeft.ascendorId' => $locationId],
                    ['locations.id' => null]
                ]
            ];
            $selectParams['distinct'] = true;
        }

        if (empty($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }
        if (empty($selectParams['customJoin'])) {
            $selectParams['customJoin'] = '';
        }

        $selectParams['customWhere'] .= " AND real_estate_request.id NOT IN (SELECT request_id FROM opportunity WHERE property_id = ".$pdo->quote($entity->id)." AND deleted = 0)";

        $selectParams['customJoin'] .= "
            LEFT JOIN real_estate_property_real_estate_request AS requestsMiddle
            ON
            requestsMiddle.real_estate_request_id = real_estate_request.id AND
            requestsMiddle.deleted = 0 AND
            requestsMiddle.real_estate_property_id = ".$pdo->quote($entity->id)."
        ";
        $selectParams['additionalSelectColumns'] = [
            'requestsMiddle.interest_degree' => 'interestDegree'
        ];

        $primaryFilter = null;
        switch ($entity->get('requestType')) {
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

        if ($entity->get('type')) {
            $selectParams['whereClause']['propertyType'] = $entity->get('type');
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields'], []);
        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) continue;
            $fromField = 'from' . ucfirst($field);
            $toField = 'to' . ucfirst($field);

            if ($entity->get($field) !== null) {
                $selectParams['whereClause'][] = [
                    'OR' => [
                        [
                            $fromField . '!=' => null,
                            $toField . '!=' => null,
                            $fromField . '<=' => $entity->get($field),
                            $toField . '>=' => $entity->get($field)
                        ],
                        [
                            $fromField . '!=' => null,
                            $toField . '=' => null,
                            $fromField . '<=' => $entity->get($field),
                        ],
                        [
                            $fromField . '=' => null,
                            $toField . '!=' => null,
                            $toField . '>=' => $entity->get($field),
                        ],
                        [
                            $fromField . '=' => null,
                            $toField . '=' => null
                        ]
                    ]
                ];
            } else {
                $selectParams['whereClause'][] = [
                    $fromField . '=' => null,
                    $toField . '=' => null
                ];
            }
        }

        if ($entity->get('price') !== null) {
            $defaultCurrency = $this->getConfig()->get('defaultCurrency');

            $price = $entity->get('price');
            $priceCurrency = $entity->get('priceCurrency');
            if ($defaultCurrency !== $priceCurrency) {
                $rates = $this->getConfig()->get('currencyRates');
                $rate1 = $this->getConfig()->get('currencyRates.' . $priceCurrency, 1.0);

                $rate1 = 1.0;
                if (!empty($rates[$priceCurrency])) {
                    $rate1 = $rates[$priceCurrency];
                }
                $rate2 = 1.0;
                if (!empty($rates[$defaultCurrency])) {
                    $rate2 = $rates[$defaultCurrency];
                }
                $price = $price * ($rate1);
                $price = $price / ($rate2);
            }

            $selectParams['whereClause'][] = array(
                'OR' => [
                    array(
                        'fromPrice!=' => null,
                        'toPrice!=' => null,
                        'fromPriceConverted<=' => $price,
                        'toPriceConverted>=' => $price
                    ),
                    array(
                        'fromPrice!=' => null,
                        'toPrice=' => null,
                        'fromPriceConverted<=' => $price,
                    ),
                    array(
                        'fromPrice=' => null,
                        'toPrice!=' => null,
                        'toPriceConverted>=' => $price,
                    ),
                    array(
                        'fromPrice=' => null,
                        'toPrice=' => null
                    )
                ]
            );
        } else {
            $selectParams['whereClause'][] = array(
                'fromPrice=' => null,
                'toPrice=' => null
            );
        }

        return $selectParams;
    }

    public function findLinkedEntitiesMatchingRequests($id, $params, $customOrder = false)
    {
        $entity = $this->getRepository()->get($id);
        $this->loadAdditionalFields($entity);

        $selectParams = $this->getMatchingRequestsSelectParams($entity, $params);

        if (!$customOrder) {
            $selectParams['orderBy'] = [
                ['requestsMiddle.interest_degree'],
                ['LIST:status:New,Assigned,In Process'],
                ['createdAt', 'DESC']
            ];
        }

        $collection = $this->getEntityManager()->getRepository('RealEstateRequest')->find($selectParams);
        $recordService = $this->getRecordService('RealEstateRequest');

        foreach ($collection as $e) {
            $recordService->loadAdditionalFieldsForList($e);
            $recordService->prepareEntityForOutput($e);
        }

        $total = $this->getEntityManager()->getRepository('RealEstateRequest')->count($selectParams);

        if ($entity->isActual()) {
            $entity->set('matchingRequestCount', $total);
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

    public function setNotIntereseted($propertyId, $requestId)
    {
        $property = $this->getEntity($propertyId);
        if (!$property) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($property, 'edit')) {
            throw new Forbidden();
        }
        return $this->getEntityManager()->getRepository('RealEstateProperty')->relate($property, 'requests', $requestId, array(
            'interestDegree' => 0
        ));
    }

    public function unsetNotIntereseted($propertyId, $requestId)
    {
        $property = $this->getEntity($propertyId);
        if (!$property) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($property, 'edit')) {
            throw new Forbidden();
        }
        return $this->getEntityManager()->getRepository('RealEstateProperty')->unrelate($property, 'requests', $requestId);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        $matchingRequestCount = null;
        if ($entity->isActual()) {
            $selectParams = $this->getMatchingRequestsSelectParams($entity, []);
            $matchingRequestCount = $this->getEntityManager()->getRepository('RealEstateRequest')->count($selectParams);
        }
        $entity->set('matchingRequestCount', $matchingRequestCount);
    }

    public function updateMatchingCount()
    {
        $repository = $this->getEntityManager()->getRepository('RealEstateProperty');

        $notActualList = $repository->select(['id', 'matchingRequestCount'])->where([
            'status' => ['Completed', 'Canceled', 'Lost'],
            'matchingRequestCount!=' => null
        ])->find();

        foreach ($notActualList as $e) {
            $e->set('matchingRequestCount', null);
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
            $selectParams = $this->getMatchingRequestsSelectParams($e, []);
            $matchingRequestCount = $this->getEntityManager()->getRepository('RealEstateRequest')->count($selectParams);
            $e->set('matchingRequestCount', $matchingRequestCount);
            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true
            ]);
        }
    }
}
