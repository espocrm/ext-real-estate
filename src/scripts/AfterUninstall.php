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

class AfterUninstall
{
    protected $container;

    public function run($container)
    {
        $this->container = $container;
        $config = $this->container->get('config');

        $tabList = $config->get('tabList', []);
        $quickCreateList = $config->get('quickCreateList', []);
        $globalSearchEntityList = $config->get('globalSearchEntityList', []);

        foreach ($tabList as $i => $item) {
            if ($item == 'RealEstateRequest' || $item == 'RealEstateProperty') {
                unset($tabList[$i]);
            }
        }
        $tabList = array_values($tabList);

        foreach ($quickCreateList as $i => $item) {
            if ($item == 'RealEstateRequest' || $item == 'RealEstateProperty') {
                unset($quickCreateList[$i]);
            }
        }
        $quickCreateList = array_values($quickCreateList);

        foreach ($globalSearchEntityList as $i => $item) {
            if ($item == 'RealEstateRequest' || $item == 'RealEstateProperty') {
                unset($globalSearchEntityList[$i]);
            }
        }
        $globalSearchEntityList = array_values($globalSearchEntityList);

        $config->set('tabList', $tabList);
        $config->set('quickCreateList', $quickCreateList);
        $config->set('globalSearchEntityList', $globalSearchEntityList);

        if ($config->get('dashboardLayoutBeforeRealEstate')) {
            $config->set('dashboardLayout', $config->get('dashboardLayoutBeforeRealEstate'));
            $config->remove('dashboardLayoutBeforeRealEstate');
        }

        $config->save();

        $this->clearCache();

        $entityManager = $container->get('entityManager');
        if ($job = $entityManager->getRepository('ScheduledJob')->where(['job' => 'PropertyMatchingUpdate'])->findOne()) {
            $entityManager->removeEntity($job);
        }
        if ($job = $entityManager->getRepository('ScheduledJob')->where(['job' => 'SendPropertyMatches'])->findOne()) {
            $entityManager->removeEntity($job);
        }
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {}
    }
}
