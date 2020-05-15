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

Espo.define('real-estate:views/real-estate-property/record/detail-bottom', 'views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        setupPanels: function () {
            Dep.prototype.setupPanels.call(this);

            this.panelList.push({
                name: 'matchingRequests',
                label: 'Matching Requests',
                view: 'real-estate:views/real-estate-property/record/panels/matching-requests',
                hidden: !this.isActive(),
                create: false,
                select: false,
                rowActionsView: 'real-estate:views/real-estate-property/record/row-actions/matching-requests',
                layout: 'listForProperty',
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
                        parentView.showPanel('matchingRequests');
                    } else {
                        parentView.hidePanel('matchingRequests');
                    }
                }
            }, this);

        },

        isActive: function () {
            return !~['Completed', 'Lost', 'Canceled'].indexOf(this.model.get('status'))
        }

    });

});
