define('real-estate:views/real-estate-property/record/panels/current-sale', ['views/base'], function (Dep) {
    return Dep.extend({
        template: 'real-estate:real-estate-property/record/panels/current-sale',

        data: function () {
            return {state: this.state};
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.state = 'loading';
            var self = this;
            // Server-side current-cycle resolver; never trusts list[0] ordering.
            Espo.Ajax.getRequest('RealEstateProperty/' + this.model.id + '/currentSaleCycle').then(function (res) {
                if (!res || !res.cycleId) {
                    self.state = 'no-cycle';
                    if (self.isRendered()) self.reRender();
                    return null;
                }
                return Espo.Ajax.getRequest('NgOfferCycle/' + res.cycleId + '/currentInternalQuote');
            }).then(function (data) {
                if (!data) return;
                self.state = data.status === 'NEEDS_PRICE' ? 'needs-price' : 'ok';
                self.chosenAmount = data.chosenAmount;
                self.chosenCurrency = data.chosenCurrency;
                self.chosenQuoteId = data.chosenQuoteId;
                self.asOf = data.asOf;
                self.ruleVersion = data.ruleVersion;
                if (self.isRendered()) self.reRender();
            }).catch(function () {
                self.state = 'denied';
                if (self.isRendered()) self.reRender();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.state !== 'ok' || !this.$el) return;
            var lang = this.getLanguage();
            var esc = _.escape;
            var html = '<p>' + esc(lang.translate('internalBest', 'fields', 'RealEstateProperty')) + ': ' +
                esc(this.chosenAmount || '') + ' ' + esc(this.chosenCurrency || '') +
                ' <span class="text-muted">(' + esc(this.chosenQuoteId || '') + ')</span></p>' +
                '<p class="text-muted">' + esc(this.asOf || '') + ' &middot; ' + esc(this.ruleVersion || '') + '</p>';
            this.$el.find('.current-sale-body').html(html);
        }
    });
});
