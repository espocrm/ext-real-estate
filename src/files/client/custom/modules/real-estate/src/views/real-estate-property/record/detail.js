/************************************************************************
* This file is part of EspoCRM.
*
* EspoCRM – Open Source CRM application.
* Copyright (C) 2014-2026 EspoCRM, Inc.
* Website: https://www.espocrm.com
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program. If not, see <https://www.gnu.org/licenses/>.
*
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU Affero General Public License version 3.
*
* In accordance with Section 7(b) of the GNU Affero General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
************************************************************************/

define('real-estate:views/real-estate-property/record/detail', 'views/record/detail', function (Dep) {
    /**
     * S05.7 scoped accessibility remediation: labels native dropdown-toggle icon
     * buttons that lack aria-label (Espo core does not add them). Only runs on
     * Property detail surface — does not touch upstream core. Safe no-op if label
     * already present. Contrast handled globally via accessibility.css.
     */
    return Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.patchDropdownLabels();
            var self = this;
            setTimeout(function () { self.patchDropdownLabels(); }, 750);
        },

        patchDropdownLabels: function () {
            if (!this.$el) return;
            var self = this;
            this.$el.find('button.dropdown-toggle[data-toggle="dropdown"]').each(function () {
                if ($(this).attr('aria-label')) return;
                $(this).attr('aria-label', self.getLanguage().translate('Dropdown', 'labels', 'RealEstateProperty'));
            });
            this.$el.find('a.link[href*="#RealEstateRequest/view/"]').each(function () {
                if ($(this).attr('aria-label')) return;
                var text = $(this).text().trim();
                var label = text || self.getLanguage().translate('MatchingRequest', 'labels', 'RealEstateProperty');
                $(this).attr('aria-label', label);
            });
        }
    });
});
