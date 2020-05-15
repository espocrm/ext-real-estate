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

namespace Espo\Modules\RealEstate\Hooks\RealEstateProperty;

class EmailRequester extends \Espo\Core\Hooks\Base
{
    public static $order = 16;

    protected function init()
    {
        $this->addDependency('serviceFactory');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    public function afterSave($entity)
    {
        if (!$this->getConfig()->get('realEstateEmailSending')) return;
        if (!$entity->get('type')) return;

        $toSend = $entity->isNew();

        $fieldList = [
            'type',
            'propertyType',
            'locationId',
            'price'
        ];

        foreach (
            $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'propertyTypes', $entity->get('type'), 'fieldList'], [])
            as
            $field
        ) {
            $fieldList[] = $field;
        }


        if (!$toSend) {
            foreach ($fieldList as $field) {
                if ($entity->hasFetched($field) && $entity->isAttributeChanged($field)) {
                    $toSend = true;
                    break;
                }
            }
        }

        if ($toSend) {
            $job = $this->getEntityManager()->getEntity('Job');
            $job->set([
                'serviceName' => 'RealEstateSendMatches',
                'methodName' => 'processPropertyJob',
                'data' => [
                    'targetId' => $entity->id,
                    'isUpdated' => !$entity->isNew()
                ]
            ]);
            $this->getEntityManager()->saveEntity($job);
        }
    }
}
