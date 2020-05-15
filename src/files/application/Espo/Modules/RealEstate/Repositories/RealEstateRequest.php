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

class RealEstateRequest extends \Espo\Core\Templates\Repositories\Base
{
    public function beforeSave(Entity $entity, array $options = array())
    {
        $propertyType = $entity->get('propertyType');

        $fieldList = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'propertyTypes', $propertyType, 'fieldList'], []);
        $fieldDefs = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields'], []);
        foreach ($fieldDefs as $field => $defs) {
            if (empty($defs['isMatching'])) continue;

            if (!in_array($field, $fieldList)) {
                echo $field . ' ';
                $entity->set('from' . ucfirst($field), null);
                $entity->set('to' . ucfirst($field), null);
            }
        }

        return parent::beforeSave($entity, $options);
    }

    public function afterSave(Entity $entity, array $options = array())
    {
        $result = parent::afterSave($entity, $options);
        $this->handleAfterSaveContacts($entity, $options);

        if ($entity->isNew() && !$entity->get('name')) {

            $e = $this->get($entity->id);
            $name = strval($e->get('number'));
            $name = str_pad($name, 6, '0', STR_PAD_LEFT);
            $name = 'R ' . $name;

            $e->set('name', $name);
            $this->save($e);
            $entity->set('name', $name);
            $entity->set('number', $e->get('number'));
        }

        return $result;
    }

    protected function handleAfterSaveContacts(Entity $entity, array $options = array())
    {
        $contactIdChanged = $entity->has('contactId') && $entity->get('contactId') != $entity->getFetched('contactId');

        if ($contactIdChanged) {
            $contactId = $entity->get('contactId');
            if (empty($contactId)) {
                $this->unrelate($entity, 'contacts', $entity->getFetched('contactId'));
                return;
            }
        }

        if ($contactIdChanged) {
            $pdo = $this->getEntityManager()->getPDO();

            $sql = "
                SELECT id FROM contact_real_estate_request
                WHERE
                    contact_id = ".$pdo->quote($contactId)." AND
                    real_estate_request_id = ".$pdo->quote($entity->id)." AND
                    deleted = 0
            ";
            $sth = $pdo->prepare($sql);
            $sth->execute();

            if (!$sth->fetch()) {
                $this->relate($entity, 'contacts', $contactId);
            }
        }
    }
}
