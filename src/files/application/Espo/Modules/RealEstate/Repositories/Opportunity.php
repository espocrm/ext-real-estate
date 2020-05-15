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

namespace Espo\Modules\RealEstate\Repositories;

use \Espo\ORM\Entity;

class Opportunity extends \Espo\Modules\Crm\Repositories\Opportunity
{
    public function beforeSave(Entity $entity, array $options = array())
    {
        parent::beforeSave($entity, $options);

        if (
            $entity->has('closeDate') &&
            !$entity->get('closeDate') &&
            $entity->get('stage') == 'Closed Won' &&
            (
                $entity->isFieldChanged('stage') || $entity->isNew()
            )
        ) {
            $entity->set('closeDate', date('Y-m-d'));
        }

        if ($entity->get('requestId') && $entity->get('propertyId')) {
            $request = $this->getEntityManager()->getEntity('RealEstateRequest', $entity->get('requestId'));
            $property = $this->getEntityManager()->getEntity('RealEstateProperty', $entity->get('propertyId'));
            if ($request && $property) {
                $name = $property->get('name') . ' - ' . $request->get('name');
                $entity->set('name', $name);
            }
        }

        if (!$entity->get('name')) {
            $entity->set('name', 'unnamed');
        }
    }

    public function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if (
            $entity->get('stage') == 'Closed Won' &&
            (
                $entity->isFieldChanged('stage') || $entity->isNew()
            )
        ) {
            if ($entity->get('requestId') && $entity->get('propertyId')) {
                $request = $this->getEntityManager()->getEntity('RealEstateRequest', $entity->get('requestId'));
                $request->set('status', 'Completed');
                $this->getEntityManager()->saveEntity($request);

                $property = $this->getEntityManager()->getEntity('RealEstateProperty', $entity->get('propertyId'));
                $property->set('status', 'Completed');
                $this->getEntityManager()->saveEntity($property);

                $opportunityList = $this->where(array(
                    'requestId' => $entity->get('requestId'),
                    'propertyId' => $entity->get('propertyId')
                ))->find();

                foreach ($opportunityList as $opportunity) {
                    if ($entity->id == $opportunity->id) continue;
                    $opportunity->set('stage', 'Closed Lost');
                    $opportunity->set('closeDate', date('Y-m-d'));
                    $this->save($opportunity);
                }
            }
        }

        if ($entity->isNew() && $entity->get('status') !== 'Closed Lost') {
            if ($entity->get('requestId') && $entity->get('propertyId')) {
                $note = $this->getEntityManager()->getEntity('Note');
                $note->set(array(
                    'type' => 'CreateRelated',
                    'parentId' => $entity->get('propertyId'),
                    'parentType' => 'RealEstateProperty',
                    'data' => array(
                        'action' => 'created',
                    ),
                    'relatedId' => $entity->id,
                    'relatedType' => 'Opportunity'
                ));
                $this->getEntityManager()->saveEntity($note);

                $note = $this->getEntityManager()->getEntity('Note');
                $note->set(array(
                    'type' => 'CreateRelated',
                    'parentId' => $entity->get('requestId'),
                    'parentType' => 'RealEstateRequest',
                    'data' => array(
                        'action' => 'created',
                    ),
                    'relatedId' => $entity->id,
                    'relatedType' => 'Opportunity'
                ));
                $this->getEntityManager()->saveEntity($note);
            }
        }

        if ($entity->isNew() || $entity->isFieldChanged('stage')) {
            if ($entity->get('requestId') && $entity->get('propertyId')) {
                $property = $this->getEntityManager()->getEntity('RealEstateProperty', $entity->get('propertyId'));
                if ($property) {
                    if ($entity->get('stage') !== 'Closed Lost') {
                        $this->getEntityManager()->getRepository('RealEstateProperty')->unrelate($property, 'requests', $entity->get('requestId'));
                    } else {
                        $this->getEntityManager()->getRepository('RealEstateProperty')->relate($property, 'requests', $entity->get('requestId'), array(
                            'interestDegree' => 0
                        ));
                    }
                }
            }
        }
    }
}

