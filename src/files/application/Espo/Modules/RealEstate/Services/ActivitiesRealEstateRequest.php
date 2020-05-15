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

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Forbidden;

use \Espo\ORM\Entity;

use \PDO;

class ActivitiesRealEstateRequest extends \Espo\Core\Services\Base
{
    protected function init()
    {
        $this->addDependencyList([
            'metadata',
            'acl',
            'selectManagerFactory',
            'serviceFactory'
        ]);
    }

    protected function getPDO()
    {
        return $this->getEntityManager()->getPDO();
    }

    protected function getEntityManager()
    {
        return $this->getInjection('entityManager');
    }

    protected function getUser()
    {
        return $this->getInjection('user');
    }

    protected function getAcl()
    {
        return $this->getInjection('acl');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getSelectManagerFactory()
    {
        return $this->getInjection('selectManagerFactory');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    public function getActivitiesMeetingQuery(Entity $entity, $statusList, $isHistory)
    {
        $scope = $entity->getEntityType();

        $selectManager = $this->getSelectManagerFactory()->create('Meeting');

        if ($this->getMetadata()->get(['entityDefs', 'Meeting', 'fields', 'isAllDay'])) {
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
                ['VALUE:', 'hasAttachment']
            ];
        } else {
            $select = [
                'id',
                'name',
                ['dateStart', 'dateStart'],
                ['dateEnd', 'dateEnd'],
                ['VALUE:Meeting', '_scope'],
                'assignedUserId',
                'assignedUserName',
                'parentType',
                'parentId',
                'status',
                'createdAt'
            ];
        }

        $baseSelectParams = [
            'select' => $select,
            'customWhere' => " AND
                (
                    (
                        meeting.parent_type = 'RealEstateRequest' AND meeting.parent_id = ".$this->getPDO()->quote($entity->id)."
                    )
                    OR
                    (
                        meeting.parent_type = 'Opportunity' AND meeting.parent_id IN (
                            SELECT opportunity.id FROM opportunity WHERE opportunity.request_id = ".$this->getPDO()->quote($entity->id)."
                        )
                    )
                )
            ",
            'whereClause' => [],
            'customJoin' => ''
        ];

        if (!empty($statusList)) {
            $baseSelectParams['whereClause'][] = [
                'status' => $statusList
            ];
        }

        $selectParams = $baseSelectParams;

        $selectManager->applyAccess($selectParams);

        $sql = $this->getEntityManager()->getQuery()->createSelectQuery('Meeting', $selectParams);

        return $sql;
    }

    public function getActivitiesCallQuery(Entity $entity, $statusList, $isHistory)
    {
        $scope = $entity->getEntityType();

        $selectManager = $this->getSelectManagerFactory()->create('Call');

        if ($this->getMetadata()->get(['entityDefs', 'Meeting', 'fields', 'isAllDay'])) {
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
                ['VALUE:', 'hasAttachment']
            ];
        } else {
            $select = [
                'id',
                'name',
                ['dateStart', 'dateStart'],
                ['dateEnd', 'dateEnd'],
                ['VALUE:Call', '_scope'],
                'assignedUserId',
                'assignedUserName',
                'parentType',
                'parentId',
                'status',
                'createdAt'
            ];
        }

        $baseSelectParams = [
            'select' => $select,
            'customWhere' => " AND
                (
                    (
                        call.parent_type = 'RealEstateRequest' AND call.parent_id = ".$this->getPDO()->quote($entity->id)."
                    )
                    OR
                    (
                        call.parent_type = 'Opportunity' AND call.parent_id IN (
                            SELECT opportunity.id FROM opportunity WHERE opportunity.request_id = ".$this->getPDO()->quote($entity->id)."
                        )
                    )
                )
            ",
            'whereClause' => [],
            'customJoin' => ''
        ];

        if (!empty($statusList)) {
            $baseSelectParams['whereClause'][] = [
                'status' => $statusList
            ];
        }

        $selectParams = $baseSelectParams;

        $selectManager->applyAccess($selectParams);

        $sql = $this->getEntityManager()->getQuery()->createSelectQuery('Call', $selectParams);

        return $sql;
    }

    public function getActivitiesEmailQuery(Entity $entity, $statusList, $isHistory)
    {
        $scope = $entity->getEntityType();

        $selectManager = $this->getSelectManagerFactory()->create('Email');

        if ($this->getMetadata()->get(['entityDefs', 'Meeting', 'fields', 'isAllDay'])) {
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
                'hasAttachment'
            ];
        } else {
            $select = [
                'id',
                'name',
                ['dateSent', 'dateStart'],
                ['VALUE:', 'dateEnd'],
                ['VALUE:Email', '_scope'],
                'assignedUserId',
                'assignedUserName',
                'parentType',
                'parentId',
                'status',
                'createdAt'
            ];
        }

        $baseSelectParams = [
            'select' => $select,
            'customWhere' => " AND
                (
                    (
                        email.parent_type = 'RealEstateRequest' AND email.parent_id = ".$this->getPDO()->quote($entity->id)."
                    )
                    OR
                    (
                        email.parent_type = 'Opportunity' AND email.parent_id IN (
                            SELECT opportunity.id FROM opportunity WHERE opportunity.request_id = ".$this->getPDO()->quote($entity->id)."
                        )
                    )
                )
            ",
            'whereClause' => [],
            'customJoin' => ''
        ];

        if (!empty($statusList)) {
            $baseSelectParams['whereClause'][] = [
                'status' => $statusList
            ];
        }

        $selectParams = $baseSelectParams;

        $selectManager->applyAccess($selectParams);

        $sql = $this->getEntityManager()->getQuery()->createSelectQuery('Email', $selectParams);

        return $sql;
    }
}
