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
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Config;
use Espo\Modules\Crm\Entities\Call;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\RealEstate\Entities\RealEstateProperty as RealEstatePropertyEntity;
use Espo\ORM\Entity;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\SelectBuilder;
use Espo\Core\Select\SelectBuilderFactory;

class ActivitiesRealEstateProperty
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
            ->from(Meeting::ENTITY_TYPE)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select($select)
            ->where([
                'OR' => [
                    [
                        'parentType' => RealEstatePropertyEntity::ENTITY_TYPE,
                        'parentId' => $entity->getId(),
                    ],
                    [
                        'parentType' => Opportunity::ENTITY_TYPE,
                        'parentId=s' => SelectBuilder::create()
                            ->from(Opportunity::ENTITY_TYPE)
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
        $select =[
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
            ->from(Call::ENTITY_TYPE)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select($select)
            ->where([
                'OR' => [
                    [
                        'parentType' => 'RealEstateProperty',
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
                        'parentType' => 'RealEstateProperty',
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
