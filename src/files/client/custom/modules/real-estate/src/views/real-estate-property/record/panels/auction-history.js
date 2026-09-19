define('real-estate:views/real-estate-property/record/panels/auction-history', ['views/base'], function (Dep) {
    return Dep.extend({
        template: 'real-estate:real-estate-property/record/panels/auction-history',

        data: function () {
            return {state: this.state};
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.state = 'loading';
            this.history = [];
            this.full = false;
            var self = this;
            Espo.Ajax.getRequest('RealEstateProperty/' + this.model.id + '/auctionHistory').then(function (data) {
                self.history = data && data.history || [];
                self.full = data && data.projection === 'full-historical';
                self.state = self.history.length ? 'ok' : 'empty';
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
            var html = '';
            this.history.forEach(function (group) {
                html += '<h5>' + esc(group.session.code || '') + ' — ' + esc(group.session.name || '') + '</h5>';
                html += '<table class="table table-bordered"><thead><tr>';
                html += '<th>' + esc(lang.translate('lotCode', 'fields', 'NgAuctionLot')) + '</th>';
                html += '<th>' + esc(lang.translate('resultState', 'fields', 'NgAuctionLot')) + '</th>';
                if (this.full) html += '<th>' + esc(lang.translate('winningAmount', 'fields', 'NgAuctionLot')) + '</th>';
                html += '</tr></thead><tbody>';
                group.lots.forEach(function (lot) {
                    var state = lang.translateOption(lot.resultState, 'resultState', 'NgAuctionLot') || lot.resultState;
                    html += '<tr><td>' + esc(lot.lotCode || '') + '</td><td>' + esc(state) + '</td>';
                    if (this.full) {
                        var historicalAmount = lot.winningAmount;
                        if (historicalAmount == null && lot.latestResult) historicalAmount = lot.latestResult.winningAmount;
                        html += '<td>' + esc(historicalAmount == null ? '' : String(historicalAmount) + ' ' + (lot.currency || (lot.latestResult && lot.latestResult.currency) || '')) + '</td>';
                    }
                    html += '</tr>';
                }, this);
                html += '</tbody></table>';
            }, this);
            this.$el.find('.auction-history-body').html(html);
        }
    });
});
