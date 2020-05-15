/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
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

Espo.define('real-estate:views/real-estate-request/record/panels/matching-properties', 'views/record/panels/relationship', function (Dep) {

    return Dep.extend({

        scope: 'RealEstateProperty',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', function () {
                this.collection.fetch();
            }, this);
        },

        setupActions: function () {
            Dep.prototype.setupActions.call(this);

            if (Dep.prototype.actionViewRelatedList) {
                var index = -1;
                this.actionList.forEach(function (item, i) {
                    if (item.action == 'listMatching') {
                        index = i;
                    }
                }, this);
                if (~index) {
                    this.actionList.splice(index, 1);
                }
            }
        },

        actionSetInterested: function (data) {
            var id = data.id;

            var model = this.collection.get(id);
            if (!model) return;

            this.notify('Please wait...');

            this.getModelFactory().create('RealEstateProperty', function (model) {
                model.id = id;
                this.listenToOnce(model, 'sync', function () {
                    this.notify(false);

                    var attributes = {};
                    attributes.propertyId = model.id;
                    attributes.propertyName = model.get('name');
                    attributes.requestId = this.model.id;
                    attributes.requestName = this.model.get('name');
                    attributes.name = attributes.propertyName + ' - ' + attributes.requestName;
                    attributes.amountCurrency = model.get('priceCurrency');

                    var markupParamName = Espo.Utils.lowerCaseFirst(this.model.get('type') || '') + 'Markup';
                    attributes.amount = model.get('price') * (this.getConfig().get(markupParamName) || 0) / 100.0;
                    attributes.amount = Math.round(attributes.amount * 100) / 100;

                    var contactIdList = Espo.Utils.clone(this.model.get('contactsIds') || []);
                    attributes.contactsIds = contactIdList;
                    attributes.contactsNames = this.model.get('contactsNames') || {};

                    attributes.contactsColumns = {};
                    contactIdList.forEach(function (id) {
                        attributes.contactsColumns[id] = {role: 'Requester'};
                    }, this);

                    var mContactIdList = model.get('contactsIds') || [];
                    var mContactNames = model.get('contactsNames') || {};
                    var mContactColumns = model.get('contactsColumns') || {};

                    mContactIdList.forEach(function (id) {
                        if (~attributes.contactsIds.indexOf(id)) return;
                        attributes.contactsIds.push(id);
                        attributes.contactsNames[id] = mContactNames[id] || 'Unknown';
                        attributes.contactsColumns[id] = {role: (mContactColumns[id] || {}).role || null};
                    }, this);

                    this.createView('modal', 'views/modals/edit', {
                        scope: 'Opportunity',
                        attributes: attributes
                    }, function (view) {
                        view.render();

                        this.listenTo(view, 'after:save', function () {
                            this.collection.fetch();

                            if (this.getParentView() && this.getParentView().getView('opportunities')) {
                                this.getParentView().getView('opportunities').actionRefresh();
                            }
                        }, this);
                    }, this);
                }, this);

                model.fetch();

            }, this);
        },

        actionSetNotInterested: function (data) {
            var id = data.id;

            var model = this.collection.get(id);
            if (!model) return;

            model.set('interestDegree', 0);

            $.ajax({
                url: 'RealEstateRequest/action/setNotInterested',
                type: 'POST',
                data: JSON.stringify({
                    propertyId: model.id,
                    requestId: this.model.id
                })
            }).done(function () {
                model.set('interestDegree', 0);
            });
        },

        actionUnsetNotInterested: function (data) {
            var id = data.id;

            var model = this.collection.get(id);
            if (!model) return;

            model.set('interestDegree', null);

            $.ajax({
                url: 'RealEstateRequest/action/unsetNotInterested',
                type: 'POST',
                data: JSON.stringify({
                    propertyId: model.id,
                    requestId: this.model.id
                })
            }).done(function () {
                model.set('interestDegree', null);
            });
        },

        actionListMatching: function () {
            this.getRouter().navigate('#RealEstateRequest/listMatching?id=' + this.model.id, {trigger: true});
        },

        actionViewRelatedList: function (data) {
            data.viewOptions = {
                listViewUrl: '#RealEstateRequest/listMatching?id=' + this.model.id
            };
            Dep.prototype.actionViewRelatedList.call(this, data);
        }
    });

});
