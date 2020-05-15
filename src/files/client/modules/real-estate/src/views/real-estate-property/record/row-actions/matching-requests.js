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

Espo.define('real-estate:views/real-estate-property/record/row-actions/matching-requests', 'views/record/row-actions/relationship', function (Dep) {

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
                action: 'viewRelated',
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
