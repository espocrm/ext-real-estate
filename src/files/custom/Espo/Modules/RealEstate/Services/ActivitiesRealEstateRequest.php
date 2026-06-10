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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\Core\Select\SelectBuilderFactory;

class ActivitiesRealEstateRequest
{
    public function __construct(
        private SelectBuilderFactory $selectBuilderFactory,
        private Config $config,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function getActivitiesMeetingQuery(Entity $entity, array $statusList): Select
    {
        $select = [
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
            ['false', 'hasAttachment'],
        ];

        if ($this->toAddFrom()) {
            $select = [
                ...$select,
                ['null', 'fromEmailAddressName'],
                ['null', 'fromString'],
            ];
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from('Meeting')
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select($select)
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

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function getActivitiesCallQuery(Entity $entity, array $statusList): Select
    {
        $select = [
            'id',
            'name',
            ['dateStart', 'dateStart'],
            ['dateEnd', 'dateEnd'],
            ['null', 'dateStartDate'],
            ['null', 'dateEndDate'],
            ['"Call"', '_scope'],
            'assignedUserId',
            'assignedUserName',
            'parentType',
            'parentId',
            'status',
            'createdAt',
            ['false', 'hasAttachment'],
        ];

        if ($this->toAddFrom()) {
            $select = [
                ...$select,
                ['null', 'fromEmailAddressName'],
                ['null', 'fromString'],
            ];
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from('Call')
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select($select)
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

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function getActivitiesEmailQuery(Entity $entity, array $statusList): Select
    {
        $select = [
            'id',
            'name',
            ['dateSent', 'dateStart'],
            ['null', 'dateEnd'],
            ['null', 'dateStartDate'],
            ['null', 'dateEndDate'],
            ['"Email"', '_scope'],
            'assignedUserId',
            'assignedUserName',
            'parentType',
            'parentId',
            'status',
            'createdAt',
            'hasAttachment',
        ];

        if ($this->toAddFrom()) {
            $select = [
                ...$select,
                'fromEmailAddressName',
                'fromString',
            ];
        }

        $builder = $this->selectBuilderFactory
            ->create()
            ->from('Email')
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select($select)
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

    private function toAddFrom(): bool
    {
        return version_compare($this->config->get('version'), '9.2.0') >= 0 ||
            $this->config->get('version') === '@@version';
    }
}
