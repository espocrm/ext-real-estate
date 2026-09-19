define('real-estate:views/real-estate-property/record/panels/commission-panel', ['views/base'], function (Dep) {
    return Dep.extend({
        template: 'real-estate:real-estate-property/record/panels/commission-panel',
        data: function () { return {state: this.state}; },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.state = 'loading';
            this.revisions = [];
            this.full = false;
            var self = this;
            Espo.Ajax.getRequest('RealEstateProperty/' + this.model.id + '/commissionHistory').then(function (data) {
                self.revisions = data && data.revisions || [];
                self.full = data && data.projection === 'full-authorized';
                self.state = self.revisions.length ? 'ok' : 'empty';
                if (self.isRendered()) self.reRender();
            }).catch(function (error) {
                self.state = error && error.status === 403 ? 'denied' : 'error';
                if (self.isRendered()) self.reRender();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.state !== 'ok' || !this.$el) return;
            var lang = this.getLanguage();
            var esc = _.escape;
            var html = '<table class="table table-bordered"><thead><tr>';
            html += '<th>' + esc(lang.translate('revisionNumber', 'fields', 'NgCommissionRevision')) + '</th>';
            if (this.full) {
                html += '<th>' + esc(lang.translate('method', 'fields', 'NgCommissionRevision')) + '</th>';
                html += '<th>' + esc(lang.translate('expectedAmount', 'fields', 'NgCommissionRevision')) + '</th>';
                html += '<th>' + esc(lang.translate('payer', 'fields', 'NgCommissionRevision')) + '</th>';
            }
            html += '</tr></thead><tbody>';
            this.revisions.forEach(function (item) {
                html += '<tr><td>' + esc(String(item.revisionNumber || '')) + '</td>';
                if (this.full) {
                    var method = lang.translateOption(item.method, 'method', 'NgCommissionRevision') || item.method || '';
                    var payer = lang.translateOption(item.payer, 'payer', 'NgCommissionRevision') || item.payer || '';
                    html += '<td>' + esc(method) + '</td><td>' + esc(item.expectedAmount == null ? '' : String(item.expectedAmount) + ' ' + (item.currency || '')) + '</td><td>' + esc(payer) + '</td>';
                }
                html += '</tr>';
            }, this);
            html += '</tbody></table>';
            this.$el.find('.commission-panel-body').html(html);
        }
    });
});
