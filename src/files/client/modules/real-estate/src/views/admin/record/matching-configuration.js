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

Espo.define('real-estate:views/admin/record/matching-configuration', 'views/record/base', function (Dep) {

    return Dep.extend({

        template: 'real-estate:admin/record/matching-configuration',

        data: function () {
            var data = {};
            data.typeDataList = this.typeDataList;
            return data;
        },

        events: {
            'click .button-container [data-action="cancel"]': function () {
                this.actionCancel();
            },
            'click .button-container [data-action="save"]': function () {
                this.actionSave();
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = this.model;
            var fieldTypeList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', 'RealEstateProperty', 'matchingFieldTypeList'], []));
            var availableFieldList = this.availableFieldList = [];

            availableFieldList.sort(function (v1, v2) {
                return this.translate(v1, 'fields', 'RealEstateProperty').localeCompare(this.translate(v2, 'fields', 'RealEstateProperty'));
            }.bind(this));

            var fieldDefs = this.getMetadata().get(['entityDefs', 'RealEstateProperty', 'fields']) || {};
            for (var field in fieldDefs) {
                var item = fieldDefs[field];
                if (item.matchingDisabled) continue;

                var fieldType = item.type;
                if (~fieldTypeList.indexOf(fieldType)) {
                    availableFieldList.push(field);
                }
            }

            var typeList = this.typeList = this.getMetadata().get(['entityDefs', 'RealEstateProperty', 'fields', 'type', 'options']) || [];

            this.typeDataList = [];
            typeList.forEach(function (type) {
                var attribute = 'fieldList_' + type;
                var typeFieldList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', 'RealEstateProperty', 'propertyTypes', type, 'fieldList']) || []);
                model.set(attribute, typeFieldList);
                var o = {
                    name: type,
                    fieldName: attribute,
                    fieldKey: attribute + 'Field',
                    labelText: this.translate(type, 'type', 'RealEstateProperty')
                };
                this.createField(attribute, 'views/fields/multi-enum', {
                    options: availableFieldList,
                    translation: 'RealEstateProperty.fields'
                }, 'edit');
                this.typeDataList.push(o);
            }, this);
        },

        actionSave: function () {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
            this.disableButtons();

            this.model.save().then(function () {
                this.getMetadata().load(function () {
                    this.getMetadata().storeToCache();
                    Espo.Ui.success(this.translate('Saved'));
                    this.enableButtons();
                }.bind(this), true);
            }.bind(this)).fail(function () {
                this.enableButtons();
            }.bind(this));
        },

        actionCancel: function () {
            this.getRouter().navigate('#Admin', {trigger: true});
        },

        enableButtons: function () {
            this.$el.find(".button-container button").removeAttr('disabled');
        },

        disableButtons: function () {
            this.$el.find(".button-container button").attr('disabled', 'disabled');
        }
    });
});