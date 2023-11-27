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
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;

use Espo\Core\Select\SelectManagerFactory;
use Espo\Core\Select\SelectBuilderFactory;

use Espo\Core\Utils\Metadata;

class ActivitiesRealEstateRequest
{
    protected $metadata;

    protected $selectManagerFactory;

    private $selectBuilderFactory;

    public function __construct(
        Metadata $metadata,
        SelectManagerFactory $selectManagerFactory,
        SelectBuilderFactory $selectBuilderFactory
    ) {
        $this->metadata = $metadata;
        $this->selectManagerFactory = $selectManagerFactory;
        $this->selectBuilderFactory = $selectBuilderFactory;
    }

    public function getActivitiesMeetingQuery(Entity $entity, array $statusList): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from('Meeting')
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select([
                'id',
                'name',
                ['dateStart', 'dateStart'],
                ['dateEnd', 'dateEnd'],
                ['dateStartDate', 'dateStartDate'],
                ['dateEndDate', 'dateEndDate'],
                ['"Meeting"', '_scope'],
                'assignedUserId',
                'assignedUserName',
                'parentType',
                'parentId',
                'status',
                'createdAt',
                ['""', 'hasAttachment'],
            ])
            ->where([
                'OR' => [
                    [
                        'parentType' => 'RealEstateRequest',
                        'parentId' => $entity->getId(),
                    ],
                    [
                        'parentType' => 'Opportunity',
                        'parentId=s' => SelectBuilder::create()
                            ->from('Opportunity')
                            ->select('id')
                            ->where([
                                'propertyId' => $entity->getId(),
                                'deleted' => false,
                            ])
                            ->build()
                            ->getRaw(),
                    ],
                ],
            ]);

        if (count($statusList)) {
            $builder->where([
                'status' => $statusList,
            ]);
        }

        return $builder->build();
    }

    public function getActivitiesCallQuery(Entity $entity, array $statusList): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from('Call')
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select([
                'id',
                'name',
                ['dateStart', 'dateStart'],
                ['dateEnd', 'dateEnd'],
                ['""', 'dateStartDate'],
                ['""', 'dateEndDate'],
                ['"Call"', '_scope'],
                'assignedUserId',
                'assignedUserName',
                'parentType',
                'parentId',
                'status',
                'createdAt',
                ['""', 'hasAttachment'],
            ])
            ->where([
                'OR' => [
                    [
                        'parentType' => 'RealEstateRequest',
                        'parentId' => $entity->getId(),
                    ],
                    [
                        'parentType' => 'Opportunity',
                        'parentId=s' => SelectBuilder::create()
                            ->from('Opportunity')
                            ->select('id')
                            ->where([
                                'propertyId' => $entity->getId(),
                                'deleted' => false,
                            ])
                            ->build()
                            ->getRaw(),
                    ],
                ],
            ]);

        if (count($statusList)) {
            $builder->where([
                'status' => $statusList,
            ]);
        }

        return $builder->build();
    }

    public function getActivitiesEmailQuery(Entity $entity, array $statusList): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from('Email')
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select([
                'id',
                'name',
                ['dateSent', 'dateStart'],
                ['""', 'dateEnd'],
                ['""', 'dateStartDate'],
                ['""', 'dateEndDate'],
                ['"Email"', '_scope'],
                'assignedUserId',
                'assignedUserName',
                'parentType',
                'parentId',
                'status',
                'createdAt',
                'hasAttachment',
            ])
            ->where([
                'OR' => [
                    [
                        'parentType' => 'RealEstateRequest',
                        'parentId' => $entity->getId(),
                    ],
                    [
                        'parentType' => 'Opportunity',
                        'parentId=s' => SelectBuilder::create()
                            ->from('Opportunity')
                            ->select('id')
                            ->where([
                                'propertyId' => $entity->getId(),
                                'deleted' => false,
                            ])
                            ->build()
                            ->getRaw(),
                    ],
                ],
            ]);

        if (count($statusList)) {
            $builder->where([
                'status' => $statusList,
            ]);
        }

        return $builder->build();
    }
}
