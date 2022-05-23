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

define(
    'real-estate:views/real-estate-property/record/panels/matching-requests',
    'views/record/panels/relationship',
    function (Dep) {

    return Dep.extend({

        scope: 'RealEstateRequest',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', () => {
                this.collection.fetch();
            });
        },

        setupActions: function () {
            Dep.prototype.setupActions.call(this);

            if (Dep.prototype.actionViewRelatedList) {
                var index = -1;

                this.actionList.forEach((item, i) => {
                    if (item.action === 'listMatching') {
                        index = i;
                    }
                });

                if (~index) {
                    this.actionList.splice(index, 1);
                }
            }
        },

        actionSetInterested: function (data) {
            var id = data.id;

            var model = this.collection.get(id);

            if (!model) {
                return;
            }

            this.notify('Please wait...');

            this.getModelFactory().create('RealEstateRequest', (model) => {
                model.id = id;

                this.listenToOnce(model, 'sync', () => {
                    this.notify(false);

                    var attributes = {};

                    attributes.propertyId = this.model.id;
                    attributes.propertyName = this.model.get('name');
                    attributes.requestId = model.id;
                    attributes.requestName = model.get('name');
                    attributes.name = attributes.propertyName + ' - ' + attributes.requestName;
                    attributes.amountCurrency = this.model.get('priceCurrency');

                    var markupParamName = Espo.Utils.lowerCaseFirst(this.model.get('requestType') || '') + 'Markup';
                    attributes.amount = this.model.get('price') * (this.getConfig().get(markupParamName) || 0) / 100.0;
                    attributes.amount = Math.round(attributes.amount * 100) / 100;

                    var contactIdList = model.get('contactsIds') || [];
                    attributes.contactsIds = contactIdList;
                    attributes.contactsNames = model.get('contactsNames') || {};

                    attributes.contactsColumns = {};

                    contactIdList.forEach((id) => {
                        attributes.contactsColumns[id] = {role: 'Requester'};
                    });

                    var mContactIdList = this.model.get('contactsIds') || [];
                    var mContactNames = this.model.get('contactsNames') || {};
                    var mContactColumns = this.model.get('contactsColumns') || {};

                    mContactIdList.forEach((id) => {
                        attributes.contactsIds.push(id);
                        attributes.contactsNames[id] = mContactNames[id] || 'Unknown';
                        attributes.contactsColumns[id] = {role: (mContactColumns[id] || {}).role || null};
                    });

                    this.createView('modal', 'views/modals/edit', {
                        scope: 'Opportunity',
                        attributes: attributes,
                    }, (view) => {
                        view.render();

                        this.listenTo(view, 'after:save', () => {
                            this.collection.fetch();

                            if (this.getParentView() && this.getParentView().getView('opportunities')) {
                                this.getParentView().getView('opportunities').actionRefresh();
                            }
                        });
                    });
                });

                model.fetch();
            });
        },

        actionSetNotInterested: function (data) {
            var id = data.id;

            var model = this.collection.get(id);

            if (!model) {
                return;
            }

            model.set('interestDegree', 0);

            $.ajax({
                url: 'RealEstateProperty/action/setNotInterested',
                type: 'POST',
                data: JSON.stringify({
                    propertyId: this.model.id,
                    requestId: model.id,
                })
            }).then(() => {
                model.set('interestDegree', 0);
            });
        },

        actionUnsetNotInterested: function (data) {
            var id = data.id;

            var model = this.collection.get(id);

            if (!model) {
                return;
            }

            model.set('interestDegree', null);

            $.ajax({
                url: 'RealEstateProperty/action/unsetNotInterested',
                type: 'POST',
                data: JSON.stringify({
                    propertyId: this.model.id,
                    requestId: model.id,
                })
            }).then(() => {
                model.set('interestDegree', null);
            });
        },

        actionListMatching: function () {
            this.getRouter().navigate('#RealEstateProperty/listMatching?id=' + this.model.id, {trigger: true});
        },

        actionViewRelatedList: function (data) {
            data.viewOptions = {
                listViewUrl: '#RealEstateProperty/listMatching?id=' + this.model.id,
            };

            Dep.prototype.actionViewRelatedList.call(this, data);
        },

    });
});
