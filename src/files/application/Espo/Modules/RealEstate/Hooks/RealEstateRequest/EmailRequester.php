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

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;

class EmailRequester
{
    public static $order = 16;

    private $config;

    private $metadata;

    private $entityManager;

    public function __construct(Config $config, Metadata $metadata, EntityManager $entityManager)
    {
        $this->config = $config;
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
    }

    public function afterSave(Entity $entity): void
    {
        if (!$this->config->get('realEstateEmailSending')) {
            return;
        }

        if (!$entity->get('propertyType')) {
            return;
        }

        if (!$entity->get('contactId')) {
            return;
        }

        $toSend = $entity->isNew();

        $fieldList = [
            'type',
            'propertyType',
            'locationsIds',
            'fromPrice',
            'toPrice',
        ];

        $matchFieldList = $this->metadata
            ->get(['entityDefs', 'RealEstateProperty', 'propertyTypes', $entity->get('propertyType'), 'fieldList'])
            ?? [];

        foreach ($matchFieldList as $field) {
            $fieldList[] = 'from' . ucfirst($field);
            $fieldList[] = 'to' . ucfirst($field);
        }

        if (!$toSend) {
            foreach ($fieldList as $field) {
                if ($entity->hasFetched($field) && $entity->isAttributeChanged($field)) {
                    $toSend = true;

                    break;
                }
            }
        }

        if (!$toSend) {
            return;
        }

        $job = $this->entityManager->getEntity('Job');

        $job->set([
            'serviceName' => 'RealEstateSendMatches',
            'methodName' => 'processRequestJob',
            'data' => [
                'targetId' => $entity->id,
                'isUpdated' => !$entity->isNew(),
            ],
            'queue' => 'e0',
        ]);

        $this->entityManager->saveEntity($job);
    }
}
