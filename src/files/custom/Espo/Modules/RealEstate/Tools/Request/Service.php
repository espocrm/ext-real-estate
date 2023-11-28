<?php
/************************************************************************
 * This file is part of Real Estate extension for EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2024 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace Espo\Modules\RealEstate\Tools\Request;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FieldProcessing\ListLoadProcessor;
use Espo\Core\FieldProcessing\Loader\Params as ListLoadParams;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Modules\RealEstate\Entities\RealEstateProperty;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use RuntimeException;

class Service
{
    public function __construct(
        private ServiceContainer $serviceContainer,
        private Acl $acl,
        private EntityManager $entityManager,
        private Config $config,
        private SelectBuilderFactory $selectBuilderFactory,
        private Metadata $metadata,
        private ListLoadProcessor $listLoadProcessor
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function findLinkedMatchingProperties(
        string $id,
        SearchParams $params,
        bool $customOrder = false
    ): RecordCollection {

        $entity = $this->entityManager
            ->getRDBRepositoryByClass(RealEstateRequest::class)
            ->getById($id);

        $this->serviceContainer
            ->getByClass(RealEstateRequest::class)
            ->loadAdditionalFields($entity);

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

        $collection = $this->entityManager
            ->getRDBRepository(RealEstateProperty::ENTITY_TYPE)
            ->clone($query)
            ->find();

        $recordService = $this->serviceContainer->getByClass(RealEstateProperty::class);

        foreach ($collection as $e) {
            $this->listLoadProcessor->process($e, ListLoadParams::create());

            $recordService->prepareEntityForOutput($e);
        }

        $total = $this->entityManager
            ->getRDBRepository(RealEstateProperty::ENTITY_TYPE)
            ->clone($query)
            ->count();

        if ($entity->isActual()) {
            $entity->set('matchingPropertyCount', $total);

            $this->entityManager
                ->getRDBRepositoryByClass(RealEstateRequest::class)
                ->save($entity, [
                    'silent' => true,
                    'skipHooks' => true,
                    'skipAll' => true,
                ]);
        }

        return new RecordCollection($collection, $total);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function getMatchingPropertiesQuery(RealEstateRequest $entity, SearchParams $params): Select
    {
        $builder = $this->selectBuilderFactory->create();

        $builder
            ->from(RealEstateProperty::ENTITY_TYPE)
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

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        }
        catch (Error $e) {
            throw new RuntimeException($e->getMessage());
        }

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

        $fieldDefs = $this->metadata->get(['entityDefs', 'RealEstateProperty', 'fields'], []);

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

        $defaultCurrency = $this->config->get('defaultCurrency');

        if ($entity->get('fromPrice') !== null) {
            $fromPrice = $entity->get('fromPrice');
            $fromPriceCurrency = $entity->get('fromPriceCurrency');

            $rates = $this->config->get('currencyRates');

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

            $rates = $this->config->get('currencyRates');

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
    
    /**
     * @throws Forbidden
     * @throws Forbidden
     * @throws NotFound
     */
    public function setNotInterested(string $requestId, string $propertyId): void
    {
        $request = $this->serviceContainer
            ->getByClass(RealEstateRequest::class)
            ->getEntity($requestId);

        if (!$request) {
            throw new NotFound();
        }

        if (!$this->acl->check($request, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        $this->entityManager
            ->getRDBRepository(RealEstateRequest::ENTITY_TYPE)
            ->getRelation($request, 'properties')
            ->relateById($propertyId, ['interestDegree' => 0]);
    }

    /**
     * @throws Forbidden
     * @throws Forbidden
     * @throws NotFound
     */
    public function unsetNotInterested(string $requestId, string $propertyId): void
    {
        $request = $this->serviceContainer
            ->getByClass(RealEstateRequest::class)
            ->getEntity($requestId);

        if (!$request) {
            throw new NotFound();
        }

        if (!$this->acl->check($request, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        $this->entityManager
            ->getRDBRepository(RealEstateRequest::ENTITY_TYPE)
            ->getRelation($request, 'properties')
            ->unrelateById($propertyId);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function updateMatchingCount(): void
    {
        $repository = $this->entityManager->getRDBRepositoryByClass(RealEstateRequest::class);

        $notActualList = $repository
            ->select(['id', 'matchingPropertyCount'])
            ->where([
                'status' => ['Completed', 'Canceled', 'Lost'],
                'matchingPropertyCount!=' => null,
            ])
            ->find();

        foreach ($notActualList as $e) {
            $e->set('matchingPropertyCount', null);

            $repository->save($e, [
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

        $service = $this->serviceContainer->getByClass(RealEstateRequest::class);

        foreach ($actualList as $e) {
            $service->loadAdditionalFields($e);

            $query = $this->getMatchingPropertiesQuery($e, SearchParams::create());

            $matchingPropertyCount = $this->entityManager
                ->getRDBRepositoryByClass(RealEstateProperty::class)
                ->clone($query)
                ->count();

            $e->set('matchingPropertyCount', $matchingPropertyCount);

            $repository->save($e, [
                'silent' => true,
                'skipHooks' => true,
                'skipAll' => true,
            ]);
        }
    }
}
