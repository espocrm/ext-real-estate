<?php
/************************************************************************
 * This file is part of Real Estate extension for EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * Real Estate extension is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Real Estate extension is distributed in the hope that it will be useful,
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
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\RealEstate\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\ForbiddenSilent;
use Espo\Core\Exceptions\NotFound;

use Espo\ORM\Entity;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order;

use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Record\FindParams;
use Espo\Services\Record;

class RealEstateProperty extends Record
{
    protected $readOnlyAttributeList = [
        'matchingRequestCount',
    ];

    public function find(SearchParams $searchParams, ?FindParams $findParams = null): RecordCollection
    {
        $where = $searchParams->getWhere();

        $itemList = [];

        if ($where && $where->getType() === WhereItem::TYPE_AND) {
            $itemList = $where->getItemList();
        }

        foreach ($itemList as $i => $item) {
            if ($item->getAttribute() !== 'matchingRequestId') {
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

            return $this->serviceFactory
                ->create('RealEstateRequest')
                ->findLinkedMatchingProperties($item->getValue(), $searchParams, true);
        }

        return parent::find($searchParams);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function getMatchingRequestsQuery(Entity $entity, SearchParams $params): Select
    {
        $builder = $this->selectBuilderFactory->create();

        $builder
            ->from('RealEstateRequest')
            ->withStrictAccessControl()
            ->withSearchParams($params);

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
            $builder->withPrimaryFilter($primaryFilter);
        }

        $queryBuilder = $builder->buildQueryBuilder();

        $locationId = $entity->get('locationId');

        if ($locationId) {
            $queryBuilder
                ->distinct()
                ->leftJoin('locations')
                ->leftJoin(
                    'RealEstateLocationPath',
                    'realEstateLocationPathLeft',
                    [
                        'realEstateLocationPathLeft.descendorId=:' => 'locationsMiddle.realEstateLocationId',
                    ]
                )
                ->leftJoin(
                    'RealEstateLocationPath',
                    'realEstateLocationPathRight',
                    [
                        'realEstateLocationPathRight.ascendorId=:' => 'locationsMiddle.realEstateLocationId',
                    ]
                )
                ->where([
                    'OR' => [
                        ['realEstateLocationPathRight.descendorId' => $locationId],
                        ['realEstateLocationPathLeft.ascendorId' => $locationId],
                        ['locations.id' => null],
                    ]
                ]);
        }

        $queryBuilder->where(
            Cond::notIn(
                Cond::column('id'),
                (new SelectBuilder)
                    ->from('Opportunity')
                    ->select('requestId')
                    ->where([
                        'propertyId=' => $entity->getId(),
                        'deleted' => false,
                    ])
                    ->build()
            )
        );

        $queryBuilder->leftJoin(
            'RealEstatePropertyRealEstateRequest',
            'requestsMiddle',
            [
                'requestsMiddle.realEstateRequestId=:' => 'id',
                'requestsMiddle.deleted' => false,
                'requestsMiddle.realEstatePropertyId=' => $entity->getId(),
            ]
        );

        if (count($queryBuilder->build()->getSelect()) === 0) {
            $queryBuilder->select('*');
        }

        $queryBuilder
            ->select('requestsMiddle.interestDegree', 'interestDegree');

        if ($entity->get('type')) {
            $queryBuilder->where([
                'propertyType' => $entity->get('type')
            ]);
        }

        $fieldDefs = $this->metadata->get(['entityDefs', 'RealEstateProperty', 'fields'], []);

        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) {
                continue;
            }

            $fromField = 'from' . ucfirst($field);
            $toField = 'to' . ucfirst($field);

            if ($entity->get($field) !== null) {
                $queryBuilder->where([
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
                            $toField . '=' => null,
                        ]
                    ]
                ]);

                continue;
            }

            $queryBuilder->where([
                $fromField . '=' => null,
                $toField . '=' => null,
            ]);
        }

        if ($entity->get('price') !== null) {
            $defaultCurrency = $this->config->get('defaultCurrency');

            $price = $entity->get('price');
            $priceCurrency = $entity->get('priceCurrency');

            if ($defaultCurrency !== $priceCurrency) {
                $rates = $this->config->get('currencyRates');

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

            $queryBuilder->where([
                'OR' => [
                    [
                        'fromPrice!=' => null,
                        'toPrice!=' => null,
                        'fromPriceConverted<=' => $price,
                        'toPriceConverted>=' => $price,
                    ],
                    [
                        'fromPrice!=' => null,
                        'toPrice=' => null,
                        'fromPriceConverted<=' => $price,
                    ],
                    [
                        'fromPrice=' => null,
                        'toPrice!=' => null,
                        'toPriceConverted>=' => $price,
                    ],
                    [
                        'fromPrice=' => null,
                        'toPrice=' => null,
                    ],
                ],
            ]);
        }
        else {
            $queryBuilder->where([
                'fromPrice=' => null,
                'toPrice=' => null,
            ]);
        }

        return $queryBuilder->build();
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function findLinkedMatchingRequests(
        string $id,
        SearchParams $params,
        bool $customOrder = false
    ): RecordCollection {

        $entity = $this->getRepository()->getById($id);

        $this->loadAdditionalFields($entity);

        $query = $this->getMatchingRequestsQuery($entity, $params);

        if (!$customOrder) {
            $query = (new SelectBuilder())
                ->clone($query)
                ->order([
                    Order::fromString('requestsMiddle.interestDegree'),
                    Order::createByPositionInList(Expr::create('status'), ['New', 'Assigned', 'In Process']),
                    Order::fromString('createdAt')->withDesc(),
                ])
                ->build();
        }

        $collection = $this->entityManager
            ->getRDBRepository('RealEstateRequest')
            ->clone($query)
            ->find();

        $recordService = $this->getRecordService('RealEstateRequest');

        foreach ($collection as $e) {
            $recordService->loadAdditionalFieldsForList($e);
            $recordService->prepareEntityForOutput($e);
        }

        $total = $this->entityManager
            ->getRDBRepository('RealEstateRequest')
            ->clone($query)
            ->count();

        if ($entity->isActual()) {
            $entity->set('matchingRequestCount', $total);

            $this->getRepository()->save($entity, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }

        return new RecordCollection($collection, $total);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     * @throws ForbiddenSilent
     */
    public function setNotInterested(string $propertyId, string $requestId): void
    {
        $property = $this->getEntity($propertyId);

        if (!$property) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($property, 'edit')) {
            throw new Forbidden();
        }

        $this->entityManager
            ->getRDBRepository('RealEstateProperty')
            ->getRelation($property, 'requests')
            ->relateById($requestId, ['interestDegree' => 0]);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     * @throws ForbiddenSilent
     */
    public function unsetNotInterested(string $propertyId, string $requestId): void
    {
        $property = $this->getEntity($propertyId);

        if (!$property) {
            throw new NotFound();
        }

        if (!$this->acl->check($property, 'edit')) {
            throw new Forbidden();
        }

        $this->entityManager
            ->getRDBRepository('RealEstateProperty')
            ->getRelation($property, 'requests')
            ->unrelateById($requestId);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        $matchingRequestCount = null;

        if ($entity->isActual()) {
            $query = $this->getMatchingRequestsQuery($entity, SearchParams::create());

            $matchingRequestCount = $this->getEntityManager()
                ->getRDBRepository('RealEstateRequest')
                ->clone($query)
                ->count();
        }

        $entity->set('matchingRequestCount', $matchingRequestCount);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function updateMatchingCount()
    {
        $repository = $this->entityManager->getRDBRepository('RealEstateProperty');

        $notActualList = $repository
            ->select(['id', 'matchingRequestCount'])
            ->where([
                'status' => ['Completed', 'Canceled', 'Lost'],
                'matchingRequestCount!=' => null
            ])
            ->find();

        foreach ($notActualList as $e) {
            $e->set('matchingRequestCount', null);

            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }

        $actualList = $repository
            ->where([
                'status!=' => ['Completed', 'Canceled', 'Lost']
            ])
            ->find();

        foreach ($actualList as $e) {
            $this->loadAdditionalFields($e);

            $query = $this->getMatchingRequestsQuery($e, SearchParams::create());

            $matchingRequestCount = $this->entityManager
                ->getRDBRepository('RealEstateRequest')
                ->clone($query)
                ->count();

            $e->set('matchingRequestCount', $matchingRequestCount);

            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }
    }
}
