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

Espo.define('real-estate:views/admin/real-estate-settings', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        detailLayout: [
            {
                label: '',
                rows: [
                    [
                        {name: "saleMarkup"},
                        {name: "rentMarkup"},
                    ],
                    [
                        {name: "realEstateEmailSending"},
                        {name: "realEstatePropertyTemplate"},
                    ],
                    [
                        {name: "realEstateEmailSendingAssignedUserCc"},
                        false
                    ]
                ]
            }
        ],


        setup: function () {
            Dep.prototype.setup.call(this);

            this.contolEmailingFields();
            this.listenTo(this.model, 'change:realEstateEmailSending', this.contolEmailingFields, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        contolEmailingFields: function () {
            if (this.model.get('realEstateEmailSending')) {
                this.showField('realEstatePropertyTemplate');
                this.showField('realEstateEmailSendingAssignedUserCc');
                this.setFieldRequired('realEstatePropertyTemplate');
            } else {
                this.hideField('realEstatePropertyTemplate');
                this.hideField('realEstateEmailSendingAssignedUserCc');
                this.setFieldNotRequired('realEstatePropertyTemplate');
            }
        }

    });

});

