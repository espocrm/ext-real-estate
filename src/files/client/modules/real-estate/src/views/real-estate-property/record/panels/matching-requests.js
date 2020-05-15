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

Espo.define('real-estate:views/real-estate-property/record/panels/matching-requests', 'views/record/panels/relationship', function (Dep) {

    return Dep.extend({

        scope: 'RealEstateRequest',

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

            this.getModelFactory().create('RealEstateRequest', function (model) {
                model.id = id;

                this.listenToOnce(model, 'sync', function () {
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
                    contactIdList.forEach(function (id) {
                        attributes.contactsColumns[id] = {role: 'Requester'};
                    }, this);

                    var mContactIdList = this.model.get('contactsIds') || [];
                    var mContactNames = this.model.get('contactsNames') || {};
                    var mContactColumns = this.model.get('contactsColumns') || {};

                    mContactIdList.forEach(function (id) {
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
                url: 'RealEstateProperty/action/setNotInterested',
                type: 'POST',
                data: JSON.stringify({
                    propertyId: this.model.id,
                    requestId: model.id
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
                url: 'RealEstateProperty/action/unsetNotInterested',
                type: 'POST',
                data: JSON.stringify({
                    propertyId: this.model.id,
                    requestId: model.id
                })
            }).done(function () {
                model.set('interestDegree', null);
            });
        },

        actionListMatching: function () {
            this.getRouter().navigate('#RealEstateProperty/listMatching?id=' + this.model.id, {trigger: true});
        },

        actionViewRelatedList: function (data) {
            data.viewOptions = {
                listViewUrl: '#RealEstateProperty/listMatching?id=' + this.model.id
            };
            Dep.prototype.actionViewRelatedList.call(this, data);
        }

    });

});
