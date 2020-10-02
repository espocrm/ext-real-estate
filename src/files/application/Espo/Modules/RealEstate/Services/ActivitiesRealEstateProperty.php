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

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;

use Espo\{
    ORM\Entity,
    ORM\QueryParams\Select,
};

use Espo\Core\{
    Select\SelectManagerFactory,
    Utils\Metadata,
};

class ActivitiesRealEstateProperty
{
    protected $metadata;
    protected $selectManagerFactory;

    public function __construct(
        Metadata $metadata,
        SelectManagerFactory $selectManagerFactory
    ) {
        $this->metadata = $metadata;
        $this->selectManagerFactory = $selectManagerFactory;
    }

    public function getActivitiesMeetingQuery(Entity $entity, $statusList, $isHistory, $additinalSelectParams = null)
    {

        $scope = $entity->getEntityType();

        $selectManager = $this->selectManagerFactory->create('Meeting');

        $select = [
            'id',
            'name',
            ['dateStart', 'dateStart'],
            ['dateEnd', 'dateEnd'],
            ['dateStartDate', 'dateStartDate'],
            ['dateEndDate', 'dateEndDate'],
            ['VALUE:Meeting', '_scope'],
            'assignedUserId',
            'assignedUserName',
            'parentType',
            'parentId',
            'status',
            'createdAt',
            ['VALUE:', 'hasAttachment'],
        ];

        $baseSelectParams = [
            'select' => $select,
            'whereClause' => [
                [
                    'OR' => [
                        [
                            'parentType' => 'RealEstateProperty',
                            'parentId' => $entity->id,
                        ],
                        [
                            'parentType' => 'Opportunity',
                            'parentId=s' => [
                                'from' => 'Opportunity',
                                'select' => ['id'],
                                'whereClause' => [
                                    'propertyId' => $entity->id,
                                    'deleted' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($statusList)) {
            $baseSelectParams['whereClause'][] = [
                'status' => $statusList,
            ];
        }

        $selectParams = $baseSelectParams;

        $selectManager->applyAccess($selectParams);

        if ($additinalSelectParams) {
            $selectParams = $selectManager->mergeSelectParams($selectParams, $additinalSelectParams);
        }

        return Select::fromRaw($selectParams);
    }

    public function getActivitiesCallQuery(Entity $entity, $statusList, $isHistory, $additinalSelectParams = null)
    {
        $scope = $entity->getEntityType();

        $selectManager = $this->selectManagerFactory->create('Call');

        $select = [
            'id',
            'name',
            ['dateStart', 'dateStart'],
            ['dateEnd', 'dateEnd'],
            ['VALUE:', 'dateStartDate'],
            ['VALUE:', 'dateEndDate'],
            ['VALUE:Call', '_scope'],
            'assignedUserId',
            'assignedUserName',
            'parentType',
            'parentId',
            'status',
            'createdAt',
            ['VALUE:', 'hasAttachment'],
        ];

        $baseSelectParams = [
            'select' => $select,
            'whereClause' => [
                [
                    'OR' => [
                        [
                            'parentType' => 'RealEstateProperty',
                            'parentId' => $entity->id,
                        ],
                        [
                            'parentType' => 'Opportunity',
                            'parentId=s' => [
                                'from' => 'Opportunity',
                                'select' => ['id'],
                                'whereClause' => [
                                    'propertyId' => $entity->id,
                                    'deleted' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($statusList)) {
            $baseSelectParams['whereClause'][] = [
                'status' => $statusList,
            ];
        }

        $selectParams = $baseSelectParams;

        $selectManager->applyAccess($selectParams);

        if ($additinalSelectParams) {
            $selectParams = $selectManager->mergeSelectParams($selectParams, $additinalSelectParams);
        }

        return Select::fromRaw($selectParams);
    }

    public function getActivitiesEmailQuery(Entity $entity, $statusList, $isHistory, $additinalSelectParams = null)
    {
        $scope = $entity->getEntityType();

        $selectManager = $this->selectManagerFactory->create('Email');

        $select = [
            'id',
            'name',
            ['dateSent', 'dateStart'],
            ['VALUE:', 'dateEnd'],
            ['VALUE:', 'dateStartDate'],
            ['VALUE:', 'dateEndDate'],
            ['VALUE:Email', '_scope'],
            'assignedUserId',
            'assignedUserName',
            'parentType',
            'parentId',
            'status',
            'createdAt',
            'hasAttachment',
        ];

        $baseSelectParams = [
            'select' => $select,
            'whereClause' => [
                [
                    'OR' => [
                        [
                            'parentType' => 'RealEstateProperty',
                            'parentId' => $entity->id,
                        ],
                        [
                            'parentType' => 'Opportunity',
                            'parentId=s' => [
                                'from' => 'Opportunity',
                                'select' => ['id'],
                                'whereClause' => [
                                    'propertyId' => $entity->id,
                                    'deleted' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($statusList)) {
            $baseSelectParams['whereClause'][] = [
                'status' => $statusList,
            ];
        }

        $selectParams = $baseSelectParams;

        $selectManager->applyAccess($selectParams);

        if ($additinalSelectParams) {
            $selectParams = $selectManager->mergeSelectParams($selectParams, $additinalSelectParams);
        }

        return Select::fromRaw($selectParams);
    }
}
