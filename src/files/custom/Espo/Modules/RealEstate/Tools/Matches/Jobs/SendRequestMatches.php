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

namespace Espo\Modules\RealEstate\Tools\Matches\Jobs;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\Config;
use Espo\Modules\RealEstate\Entities\RealEstateProperty;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\Modules\RealEstate\Tools\Request\Service;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Order;
use RuntimeException;

/**
 * @noinspection PhpUnused
 */
class SendRequestMatches implements Job
{
    public function __construct(
        private EntityManager $entityManager,
        private Service $service,
        private Config $config,
        private ServiceContainer $serviceContainer
    ) {}

    /**
     * @inheritDoc
     */
    public function run(Data $data): void
    {
        $targetId = $data->getTargetId();

        if (!$targetId) {
            throw new RuntimeException();
        }

        $entity = $this->entityManager
            ->getRDBRepositoryByClass(RealEstateRequest::class)
            ->getById($targetId);

        if (!$entity) {
            throw new RuntimeException("Not found.");
        }

        $this->serviceContainer
            ->getByClass(RealEstateRequest::class)
            ->loadAdditionalFields($entity);

        try {
            $query = $this->service->getMatchingPropertiesQuery($entity, SearchParams::create());
        }
        catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage());
        }

        $limit = $this->config->get('realEstateEmailSendingLimit', 20);

        $propertyList = $this->entityManager
            ->getRDBRepositoryByClass(RealEstateProperty::class)
            ->clone($query)
            ->limit(0, $limit)
            ->order([
                Order::fromString('propertiesMiddle.interestDegree'),
                Order::createByPositionInList(Expr::create('status'), ['New', 'Assigned', 'In Process']),
                Order::fromString('createdAt')->withDesc(),
            ])
            ->find();

        foreach ($propertyList as $property) {
            if (
                $this->entityManager
                    ->getRDBRepository('RealEstateSendMatchesQueueItem')
                    ->where([
                        'requestId' => $entity->getId(),
                        'propertyId' => $property->getId()
                    ])
                    ->findOne()
            ) {
                continue;
            }

            $queueItem = $this->entityManager->getEntity('RealEstateSendMatchesQueueItem');

            $queueItem->set([
                'requestId' => $entity->getId(),
                'propertyId' => $property->getId()
            ]);

            $this->entityManager->saveEntity($queueItem);
        }
    }
}
