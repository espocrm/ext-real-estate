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

class AfterInstall
{
    protected $container;

    public function run($container)
    {
        $this->container = $container;

        $config = $this->container->get('config');

        $tabList = $config->get('tabList', []);

        if (!in_array('RealEstateRequest', $tabList)) {
            array_unshift($tabList, 'RealEstateRequest');
        }
        if (!in_array('RealEstateProperty', $tabList)) {
            array_unshift($tabList, 'RealEstateProperty');
        }

        $quickCreateList = $config->get('quickCreateList', []);

        if (!in_array('RealEstateRequest', $quickCreateList)) {
            array_unshift($quickCreateList, 'RealEstateRequest');
        }
        if (!in_array('RealEstateRequest', $quickCreateList)) {
            array_unshift($quickCreateList, 'RealEstateRequest');
        }

        $globalSearchEntityList = $config->get('globalSearchEntityList', []);

        if (!in_array('RealEstateRequest', $globalSearchEntityList)) {
            array_unshift($globalSearchEntityList, 'RealEstateRequest');
        }
        if (!in_array('RealEstateProperty', $globalSearchEntityList)) {
            array_unshift($globalSearchEntityList, 'RealEstateProperty');
        }

        if (!in_array('Opportunity', $globalSearchEntityList)) {
            $globalSearchEntityList[] = 'Opportunity';
        }
        if (!in_array('Contact', $globalSearchEntityList)) {
            $globalSearchEntityList[] = 'Contact';
        }
        if (!in_array('Account', $globalSearchEntityList)) {
            $globalSearchEntityList[] = 'Account';
        }

        $config->set('tabList', $tabList);
        $config->set('quickCreateList', $quickCreateList);
        $config->set('globalSearchEntityList', $globalSearchEntityList);

        if ($config->get('saleMarkup') == null) {
            $config->set('saleMarkup', 5);
        }
        if ($config->get('rentMarkup') == null) {
            $config->set('rentMarkup', 50);
        }

        if (!$config->get('dashboardLayoutBeforeRealEstate')) {
            $config->set('dashboardLayoutBeforeRealEstate', $config->get('dashboardLayout'));

            $config->set('dashboardLayout', [
                (object) [
                    'name' => 'My Espo',
                    'layout' => [
                        (object) [
                            'id' => 'reStream',
                            'name' => 'Stream',
                            'x' => 0,
                            'y' => 0,
                            'width' => 2,
                            'height' => 2
                        ],
                        (object) [
                            'id' => 'reProperties',
                            'name' => 'Properties',
                            'x' => 2,
                            'y' => 2,
                            'width' => 2,
                            'height' => 2
                        ],
                        (object) [
                            'id' => 'reRequests',
                            'name' => 'Requests',
                            'x' => 2,
                            'y' => 0,
                            'width' => 2,
                            'height' => 2
                        ],
                        (object) [
                            'id' => 'reOpportunities',
                            'name' => 'Opportunities',
                            'x' => 0,
                            'y' => 2,
                            'width' => 2,
                            'height' => 2
                        ]
                    ]
                ]
            ]);
        }

        $config->save();

        $this->clearCache();

        $entityManager = $container->get('entityManager');
        if (!$entityManager->getRepository('ScheduledJob')->where(['job' => 'PropertyMatchingUpdate'])->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set([
               'name' => 'Property Matching Update',
               'job' => 'PropertyMatchingUpdate',
               'status' => 'Active',
               'scheduling' => '55 */2 * * *'
            ]);
            $entityManager->saveEntity($job);
        }
        if (!$entityManager->getRepository('ScheduledJob')->where(['job' => 'SendPropertyMatches'])->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set([
               'name' => 'Send Matched Properties to Requestors',
               'job' => 'SendPropertyMatches',
               'status' => 'Active',
               'scheduling' => '*/2 * * * *'
            ]);
            $entityManager->saveEntity($job);
        }
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {}
    }
}
