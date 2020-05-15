<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014  Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
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
