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

namespace Espo\Modules\RealEstate\Hooks\RealEstateRequest;

use Espo\Core\Utils\Metadata;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class SetValues
{
    public function __construct(
        private Metadata $metadata,
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity): void
    {
        $propertyType = $entity->get('propertyType');

        $fieldList = $this->metadata
            ->get(['entityDefs', 'RealEstateProperty', 'propertyTypes', $propertyType, 'fieldList'], []);

        $fieldDefs = $this->metadata->get(['entityDefs', 'RealEstateProperty', 'fields'], []);

        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) {
                continue;
            }

            if (!in_array($field, $fieldList)) {
                $entity->set('from' . ucfirst($field), null);
                $entity->set('to' . ucfirst($field), null);
            }
        }
    }

    public function afterSave(Entity $entity): void
    {
        $this->handleAfterSaveContacts($entity);

        $repository = $this->entityManager->getRDBRepositoryByClass(RealEstateRequest::class);

        if ($entity->isNew() && !$entity->get('name')) {
            $e = $repository->getById($entity->getId());

            $name = strval($e->get('number'));
            $name = str_pad($name, 6, '0', STR_PAD_LEFT);
            $name = 'R ' . $name;

            $e->set('name', $name);

            $repository->save($e);

            $entity->set('name', $name);
            $entity->set('number', $e->get('number'));
        }
    }

    private function handleAfterSaveContacts(Entity $entity): void
    {
        $repository = $this->entityManager->getRDBRepositoryByClass(RealEstateRequest::class);

        $contactIdChanged =
            $entity->has('contactId') && $entity->get('contactId') != $entity->getFetched('contactId');

        if ($contactIdChanged) {
            $contactId = $entity->get('contactId');

            if (empty($contactId)) {
                $repository
                    ->getRelation($entity, 'contacts')
                    ->unrelateById($entity->getFetched('contactId'));

                return;
            }
        }

        if ($contactIdChanged) {
            $query = $this->entityManager
                ->getQueryBuilder()
                ->select('id')
                ->from('ContactRealEstateRequest')
                ->where([
                    'contactId' => $contactId,
                    'realEstateRequestId' => $entity->getId(),
                    'deleted' => false,
                ])
                ->build();

            $sth = $this->entityManager->getQueryExecutor()->execute($query);

            if (!$sth->fetch()) {
                $repository
                    ->getRelation($entity, 'contacts')
                    ->relateById($contactId);
            }
        }
    }
}
