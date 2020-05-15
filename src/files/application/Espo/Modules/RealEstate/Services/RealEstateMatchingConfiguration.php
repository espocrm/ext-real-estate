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

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\NotFound;

class RealEstateMatchingConfiguration extends \Espo\Core\Templates\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependencyList([
            'serviceFactory',
            'container',
            'baseLanguage'
        ]);
    }

    protected function getBaseLanguage()
    {
        return $this->getInjection('container')->get('baseLanguage');
    }

    public function setMatchingParameters($data)
    {
        $isMetadataChanged = false;
        $isLanguageChanged = false;

        $matchingFieldList = [];
        $previousMatchingFieldList = [];

        $propertyTypes = (object) [];

        $typeList = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields', 'type', 'options'], []);
        foreach ($typeList as $type) {
            $key = 'fieldList_' . $type;
            if (!isset($data->$key)) continue;
            if (!is_array($data->$key)) continue;
            $fieldList = $data->$key;

            $propertyTypes->$type = (object) [
                'fieldList' => $fieldList
            ];

            foreach ($fieldList as $field) {
                if (!in_array($field, $matchingFieldList)) {
                    $matchingFieldList[] = $field;
                }
            }
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields'], []);

        foreach ($fieldDefs as $field => $defs) {
            if (!empty($defs['isMatching'])) {
                $previousMatchingFieldList[] = $field;
            }
        }

        $entityDefsData = [];

        $toRemoveFieldList = [];

        foreach ($previousMatchingFieldList as $field) {
            if (!in_array($field, $matchingFieldList)) {
                if (!array_key_exists('fields', $entityDefsData)) {
                    $entityDefsData['fields'] = [];
                }
                if (!array_key_exists($field, $entityDefsData['fields'])) {
                    $entityDefsData['fields'][$field] = [];
                }
                $entityDefsData['fields'][$field]['isMatching'] = false;

                if (!empty($fieldDefs[$field]['isCustom'])) {
                    $toRemoveFieldList[] = $field;
                }
            }
        }

        foreach ($matchingFieldList as $field) {
            if (!array_key_exists('fields', $entityDefsData)) {
                $entityDefsData['fields'] = [];
            }
            if (!array_key_exists($field, $entityDefsData['fields'])) {
                $entityDefsData['fields'][$field] = [];
            }
            $entityDefsData['fields'][$field]['isMatching'] = true;
        }

        $entityDefsData['propertyTypes'] = $propertyTypes;

        if (!empty($entityDefsData)) {
            $this->getMetadata()->set('entityDefs', 'RealEstateProperty', $entityDefsData);
            $isMetadataChanged = true;
        }

        $requestFieldDefs = [];

        $fieldDefs = $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'fields'], []);
        foreach ($fieldDefs as $field => $defs) {
            if (!empty($defs['isMatching']) && !empty($defs['isCustom'])) {
                $type = $defs['type'];
                if (!in_array($type, $this->getMetadata()->get(['entityDefs', 'RealEstateProperty', 'matchingFieldTypeList'], []))) {
                    continue;
                }

                if ($type === 'int' || $type === 'float') {
                    $fromName = 'from' . ucfirst($field);
                    $toName = 'to' . ucfirst($field);
                    $requestFieldDefs[$fromName] = [
                        'type' => $type
                    ];
                    $requestFieldDefs[$toName] = [
                        'type' => $type
                    ];
                    $requestFieldDefs[$field] = [
                        'type' => 'range' . ucfirst($type)
                    ];

                    if (array_key_exists('min', $defs)) {
                        $requestFieldDefs[$fromName]['min'] = $defs['min'];
                        $requestFieldDefs[$toName]['min'] = $defs['min'];
                    }
                    if (array_key_exists('max', $defs)) {
                        $requestFieldDefs[$fromName]['max'] = $defs['max'];
                        $requestFieldDefs[$toName]['max'] = $defs['max'];
                    }

                    $label = $this->getBaseLanguage()->translate($field, 'fields', 'RealEstateProperty');
                    $minLabel = $this->getBaseLanguage()->translate('Min', 'labels', 'RealEstateRequest');
                    $maxLabel = $this->getBaseLanguage()->translate('Max', 'labels', 'RealEstateRequest');

                    $this->getBaseLanguage()->set('RealEstateRequest', 'fields', $field, $label);
                    $this->getBaseLanguage()->set('RealEstateRequest', 'fields', $fromName, $minLabel . ' ' . $label);
                    $this->getBaseLanguage()->set('RealEstateRequest', 'fields', $toName, $maxLabel . ' ' . $label);
                    $isLanguageChanged = true;
                }
            }
        }

        if (!empty($requestFieldDefs)) {
            $this->getMetadata()->set('entityDefs', 'RealEstateRequest', [
                'fields' => $requestFieldDefs
            ]);
            $isMetadataChanged = true;
        }


        $this->getMetadata()->save();

        if ($isLanguageChanged) {
            $this->getBaseLanguage()->save();
        }

        if (!empty($toRemoveFieldList)) {
            $requestCustomDefs = $this->getMetadata()->getCustom('entityDefs', 'RealEstateRequest');
            foreach ($toRemoveFieldList as $field) {
                $fromField = 'from' . ucfirst($field);
                $toField = 'to' . ucfirst($field);
                if (isset($requestCustomDefs->fields) && isset($requestCustomDefs->fields->$field)) {
                    unset($requestCustomDefs->fields->$field);
                    if (isset($requestCustomDefs->fields->$fromField)) {
                        unset($requestCustomDefs->fields->$fromField);
                    }
                    if (isset($requestCustomDefs->fields->$toField)) {
                        unset($requestCustomDefs->fields->$toField);
                    }
                }
            }
            $this->getMetadata()->saveCustom('entityDefs', 'RealEstateRequest', $requestCustomDefs);
        }

        return true;
    }
}
