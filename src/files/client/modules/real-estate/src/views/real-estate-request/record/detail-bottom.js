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

Espo.define('real-estate:views/real-estate-request/record/detail-bottom', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        setupPanels: function () {
            Dep.prototype.setupPanels.call(this);

            this.panelList.push({
                name: 'matchingProperties',
                label: 'Matching Properties',
                view: 'real-estate:views/real-estate-request/record/panels/matching-properties',
                hidden: !this.isActive(),
                create: false,
                select: false,
                rowActionsView: 'real-estate:views/real-estate-request/record/row-actions/matching-properties',
                layout: 'listForRequest',
                actionList: [{
                    name: 'listMatching',
                    label: 'List',
                    action: 'listMatching'
                }],
                order: 3
            });

            this.listenTo(this.model, 'change:status', function () {
                if (this.isRendered()) {
                    var parentView = this.getParentView();
                    if (this.isActive()) {
                        parentView.showPanel('matchingProperties');
                    } else {
                        parentView.hidePanel('matchingProperties');
                    }
                }
            }, this);

        },

        isActive: function () {
            return !~['Completed', 'Lost', 'Canceled'].indexOf(this.model.get('status'))
        }

    });

});
