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

namespace Espo\Modules\RealEstate\Hooks\Opportunity;

use Espo\Entities\Note;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\Modules\RealEstate\Entities\RealEstateProperty;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;

class RealEstate
{
    public static $order = 16;

    public function __construct(private EntityManager $entityManager)
    {}

    /**
     * @param Opportunity $entity
     * @noinspection PhpUnusedParameterInspection
     */
    public function beforeSave(Entity $entity, array $options): void
    {
        if (
            $entity->has('closeDate') &&
            !$entity->get('closeDate') &&
            $entity->get('stage') == 'Closed Won' &&
            (
                $entity->isAttributeChanged('stage') || $entity->isNew()
            )
        ) {
            $entity->set('closeDate', date('Y-m-d'));
        }

        if ($entity->get('requestId') && $entity->get('propertyId')) {
            $request = $this->entityManager
                ->getEntityById(RealEstateRequest::ENTITY_TYPE, $entity->get('requestId'));
            $property = $this->entityManager
                ->getEntityById(RealEstateProperty::ENTITY_TYPE, $entity->get('propertyId'));

            if ($request && $property) {
                $name = $property->get('name') . ' - ' . $request->get('name');

                $entity->set('name', $name);
            }
        }

        if (!$entity->get('name')) {
            $entity->set('name', 'unnamed');
        }
    }

    /**
     * @param Opportunity $entity
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterSave(Entity $entity, array $options): void
    {
        $repository = $this->entityManager->getRDBRepositoryByClass(Opportunity::class);

        if (
            $entity->get('stage') == 'Closed Won' &&
            ($entity->isAttributeChanged('stage') || $entity->isNew()) &&
            $entity->get('requestId') && $entity->get('propertyId')
        ) {
            $request = $this->entityManager
                ->getEntityById(RealEstateRequest::ENTITY_TYPE, $entity->get('requestId'));

            $request->set('status', 'Completed');

            $this->entityManager->saveEntity($request);

            $property = $this->entityManager
                ->getEntityById(RealEstateProperty::ENTITY_TYPE, $entity->get('propertyId'));

            $property->set('status', 'Completed');

            $this->entityManager->saveEntity($property);

            $opportunityList = $this
                ->entityManager
                ->getRDBRepositoryByClass(Opportunity::class)
                ->where([
                    'requestId' => $entity->get('requestId'),
                    'propertyId' => $entity->get('propertyId'),
                ])
                ->find();

            foreach ($opportunityList as $opportunity) {
                if ($entity->getId() == $opportunity->getId()) {
                    continue;
                }

                $opportunity->set('stage', 'Closed Lost');
                $opportunity->set('closeDate', date('Y-m-d'));

                $repository->save($opportunity);
            }
        }

        if (
            $entity->isNew() && $entity->get('status') !== 'Closed Lost' &&
            $entity->get('requestId') && $entity->get('propertyId')
        ) {
            $note = $this->entityManager->getNewEntity(Note::ENTITY_TYPE);

            $note->set([
                'type' => Note::TYPE_CREATE_RELATED,
                'parentId' => $entity->get('propertyId'),
                'parentType' => RealEstateProperty::ENTITY_TYPE,
                'data' => [
                    'action' => 'created',
                ],
                'relatedId' => $entity->getId(),
                'relatedType' => Opportunity::ENTITY_TYPE,
            ]);

            $this->entityManager->saveEntity($note);

            $note = $this->entityManager->getNewEntity(Note::ENTITY_TYPE);

            $note->set([
                'type' => Note::TYPE_CREATE_RELATED,
                'parentId' => $entity->get('requestId'),
                'parentType' => RealEstateRequest::ENTITY_TYPE,
                'data' => [
                    'action' => 'created',
                ],
                'relatedId' => $entity->getId(),
                'relatedType' => Opportunity::ENTITY_TYPE,
            ]);

            $this->entityManager->saveEntity($note);
        }

        if (
            ($entity->isNew() || $entity->isAttributeChanged('stage')) &&
            $entity->get('requestId') &&
            $entity->get('propertyId')
        ) {
            $property = $this->entityManager
                ->getEntityById(RealEstateProperty::ENTITY_TYPE, $entity->get('propertyId'));

            if ($property) {
                if ($entity->get('stage') !== 'Closed Lost') {
                    $this->entityManager
                        ->getRDBRepository(RealEstateProperty::ENTITY_TYPE)
                        ->getRelation($property, 'requests')
                        ->unrelateById($entity->get('requestId'));
                }
                else {
                    $this->entityManager
                        ->getRDBRepository(RealEstateProperty::ENTITY_TYPE)
                        ->getRelation($property, 'requests')
                        ->relateById($entity->get('requestId'), ['interestDegree' => 0]);
                }
            }
        }
    }
}
