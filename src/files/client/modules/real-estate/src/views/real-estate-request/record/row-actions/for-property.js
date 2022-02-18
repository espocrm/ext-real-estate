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

Espo.define('real-estate:views/real-estate-request/record/row-actions/for-property', 'views/record/row-actions/empty', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:interestDegree', function () {
                setTimeout(function () {
                    this.reRender();
                }.bind(this), 100);
            }, this);
        },

        getActionList: function () {
            var actionList = Dep.prototype.getActionList.call(this);

            var list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                }
            }];

            if (this.options.acl.edit && this.getAcl().check('Opportunity', 'edit')) {
                list.push({
                    action: 'setInterested',
                    html: this.translate('Create Opportunity', 'labels', 'RealEstateRequest'),
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (this.options.acl.edit) {
                if (this.model.get('interestDegree') !== 0) {
                    list.push({
                        action: 'setNotInterested',
                        html: this.translate('Not Interested', 'labels', 'RealEstateRequest'),
                        data: {
                            id: this.model.id
                        }
                    });
                } else {
                    list.push({
                        action: 'unsetNotInterested',
                        html: this.translate('Unset Not Interested', 'labels', 'RealEstateRequest'),
                        data: {
                            id: this.model.id
                        }
                    });
                }
            }

            return list;
        },

    });

});
