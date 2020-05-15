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

Espo.define('real-estate:property-dynamic-handler', 'dynamic-handler', function (Dep) {

    return Dep.extend({

        init: function () {
            this.onChangeType(this.model, this.model.get('type'));
        },

        onChangeType: function (model, value) {
            var type = value;

            var fieldDefs = this.getMetadata().get(['entityDefs', 'RealEstateProperty', 'fields']) || {};
            for (var field in fieldDefs) {
                var item = fieldDefs[field];
                if (item.disabled) continue;
                if (item.isMatching) {
                    this.recordView.hideField(field);
                }
            }
            var fieldList = this.getMetadata().get(['entityDefs', 'RealEstateProperty', 'propertyTypes', type, 'fieldList']) || [];

            fieldList.forEach(function (field) {
                this.recordView.showField(field);
            }, this);
        }
    });
});
