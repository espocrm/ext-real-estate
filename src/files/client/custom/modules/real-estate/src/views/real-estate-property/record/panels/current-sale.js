define('real-estate:views/real-estate-property/record/panels/current-sale', ['views/base'], function (Dep) {
    return Dep.extend({
        template: 'real-estate:real-estate-property/record/panels/current-sale',
        data: function () { return {}; },
        renderState: function (html) {
            if (this.$el && this.$el.find('.current-sale-body').length) {
                this.$el.find('.current-sale-body').html(html);
            }
        },
        setup: function () {
            Dep.prototype.setup.call(this);
            this.state = 'loading';
            this.status = null;
            this.asOf = null;
            this.ruleVersion = null;
            this.chosenAmount = null;
            this.chosenCurrency = null;
            this.chosenQuoteId = null;
            this.candidates = [];
            this.excluded = {};
            var self = this;
            var where = encodeURIComponent(JSON.stringify([
                {type: 'equals', attribute: 'propertyId', value: this.model.id},
                {type: 'equals', attribute: 'intent', value: 'SALE'}
            ]));
            Espo.Ajax.getRequest('NgOfferCycle?where=' + where + '&maxSize=1').then(function (list) {
                var cycleId = list && list.list && list.list[0] && list.list[0].id;
                if (!cycleId) {
                    self.renderState('<p>Chưa có đợt chào bán</p>');
                    return null;
                    return null;
                }
                self.cycleId = cycleId;
                return Espo.Ajax.getRequest('NgOfferCycle/' + cycleId + '/currentInternalQuote');
            }).then(function (data) {
                if (!data) return;
                if (!self.isRendered() && !self.$el) return;
                var html;
                if (data.status === 'NEEDS_PRICE') {
                    html = '<p><span class="label label-warning">CHỜ GIÁ / CẦN XÁC MINH</span></p>';
                } else {
                    html = '<p>' + (data.chosenAmount || '') + ' ' + (data.chosenCurrency || '') +
                        ' <span class="text-muted">(' + (data.chosenQuoteId || '') + ')</span></p>' +
                        '<p class="text-muted">' + (data.asOf || '') + ' &middot; ' + (data.ruleVersion || '') + '</p>';
                }
                self.renderState(html);
            }).catch(function () {
                if (self.$el) self.renderState('<p>Không có quyền truy cập</p>');
            });
        }
    });
});
