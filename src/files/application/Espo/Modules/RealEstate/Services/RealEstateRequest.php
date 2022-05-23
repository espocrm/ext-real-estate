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

use Espo\ORM\Entity;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;

use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order;

use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Record\FindParams;

class RealEstateRequest extends \Espo\Core\Templates\Services\Base
{
    protected $readOnlyAttributeList = [
        'matchingPropertyCount',
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
            $query = $this->getMatchingPropertiesQuery($entity, SearchParams::create());

            $matchingPropertyCount = $this->getEntityManager()
                ->getRDBRepository('RealEstateProperty')
                ->clone($query)
                ->count();
        }

        $entity->set('matchingPropertyCount', $matchingPropertyCount);
    }

    public function find(SearchParams $params, ?FindParams $findParams = null): RecordCollection
    {
        $where = $params->getWhere();

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

            $params = $params->withWhere($where);

            if (!$item->getValue() || $item->getType() !== 'equals') {
                continue;
            }

            return $this->getServiceFactory()
                ->create('RealEstateProperty')
                ->findLinkedMatchingRequests($item->getValue(), $params, true);
        }

        return parent::find($params);
    }

    public function getMatchingPropertiesQuery(Entity $entity, SearchParams $params): Select
    {
        $builder = $this->selectBuilderFactory->create();

        $builder
            ->from('RealEstateProperty')
            ->withStrictAccessControl()
            ->withSearchParams($params);

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
            $builder->withPrimaryFilter($primaryFilter);
        }

        $locationIdList = $entity->getLinkMultipleIdList('locations');

        $queryBuilder = $builder->buildQueryBuilder();

        //$selectParams['leftJoins'] = $selectParams['leftJoins'] ?? [];

        if (count($locationIdList)) {
            $queryBuilder
                ->distinct()
                ->leftJoin(
                    'RealEstateLocationPath',
                    'realEstateLocationPath',
                    [
                        'realEstateLocationPath.descendorId=:' => 'locationId',
                    ]
                )
                ->where([
                    'realEstateLocationPath.ascendorId' => $locationIdList
                ]);
        }

        $queryBuilder->where(
            Cond::notIn(
                Cond::column('id'),
                (new SelectBuilder)
                    ->from('Opportunity')
                    ->select('propertyId')
                    ->where([
                        'requestId=' => $entity->getId(),
                        'deleted' => false,
                    ])
                    ->build()
            )
        );

        $queryBuilder->leftJoin(
            'RealEstatePropertyRealEstateRequest',
            'propertiesMiddle',
            [
                'propertiesMiddle.realEstatePropertyId=:' => 'id',
                'propertiesMiddle.deleted' => false,
                'propertiesMiddle.realEstateRequestId=' => $entity->getId(),
            ]
        );

        if (count($queryBuilder->build()->getSelect()) === 0) {
            $queryBuilder->select('*');
        }

        $queryBuilder
            ->select('propertiesMiddle.interestDegree', 'interestDegree');

        if ($entity->get('propertyType')) {
            $queryBuilder->where([
                'type' => $entity->get('propertyType')
            ]);
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields'], []);

        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) {
                continue;
            }

            if ($entity->get('from'. ucfirst($field)) !== null) {
                $queryBuilder->where([
                    $field . '>=' => $entity->get('from' . ucfirst($field))
                ]);
            }

            if ($entity->get('to'. ucfirst($field)) !== null) {
                $queryBuilder->where([
                    $field . '<=' => $entity->get('to' . ucfirst($field))
                ]);
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

            $queryBuilder->where([
                'priceConverted>=' => $fromPrice
            ]);
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

            $queryBuilder->where([
                'priceConverted<=' => $toPrice
            ]);
        }

        return $queryBuilder->build();
    }

    public function findLinkedMatchingProperties(
        string $id,
        SearchParams $params,
        bool $customOrder = false
    ): RecordCollection {

        $entity = $this->getRepository()->get($id);

        $this->loadAdditionalFields($entity);

        $query = $this->getMatchingPropertiesQuery($entity, $params);

        if (!$customOrder) {
            $query = (new SelectBuilder())
                ->clone($query)
                ->order([
                    Order::fromString('propertiesMiddle.interestDegree'),
                    Order::createByPositionInList(Expr::create('status'), ['New', 'Assigned', 'In Process']),
                    Order::fromString('createdAt')->withDesc(),
                ])
                ->build();
        }

        $collection = $this->getEntityManager()
            ->getRDBRepository('RealEstateProperty')
            ->clone($query)
            ->find();

        $recordService = $this->getRecordService('RealEstateProperty');

        foreach ($collection as $e) {
            $recordService->loadAdditionalFieldsForList($e);
            $recordService->prepareEntityForOutput($e);
        }

        $total = $this->getEntityManager()
            ->getRDBRepository('RealEstateProperty')
            ->clone($query)
            ->count();

        if ($entity->isActual()) {
            $entity->set('matchingPropertyCount', $total);

            $this->getRepository()->save($entity, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }

        return new RecordCollection($collection, $total);
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

        return $this->getEntityManager()
            ->getRepository('RealEstateRequest')
            ->relate($request, 'properties', $propertyId, [
                'interestDegree' => 0
            ]);
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

        return $this->getEntityManager()
            ->getRepository('RealEstateRequest')
            ->unrelate($request, 'properties', $propertyId);
    }

    public function updateMatchingCount()
    {
        $repository = $this->getEntityManager()->getRDBRepository('RealEstateRequest');

        $notActualList = $repository
            ->select(['id', 'matchingPropertyCount'])
            ->where([
                'status' => ['Completed', 'Canceled', 'Lost'],
                'matchingPropertyCount!=' => null,
            ])
            ->find();

        foreach ($notActualList as $e) {
            $e->set('matchingPropertyCount', null);
            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }

        $actualList = $repository
            ->where([
                'status!=' => ['Completed', 'Canceled', 'Lost'],
            ])
            ->find();

        foreach ($actualList as $e) {
            $this->loadAdditionalFields($e);

            $query = $this->getMatchingPropertiesQuery($e, SearchParams::create());

            $matchingPropertyCount = $this->getEntityManager()
                ->getRDBRepository('RealEstateProperty')
                ->clone($query)
                ->count();

            $e->set('matchingPropertyCount', $matchingPropertyCount);

            $this->getRepository()->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }
    }
}
