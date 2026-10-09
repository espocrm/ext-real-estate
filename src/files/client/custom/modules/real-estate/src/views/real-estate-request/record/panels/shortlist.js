define('real-estate:views/real-estate-request/record/panels/shortlist', ['views/base'], function (Dep) {
    return Dep.extend({
        template: 'real-estate:real-estate-request/record/panels/shortlist',

        data: function () {
            return {
                state: this.state,
                rows: this.rows,
                lang: {
                    statusLabel: 'Trạng thái',
                    propertyLabel: 'Bất động sản',
                    fitLabel: 'Lý do phù hợp',
                    sentLabel: this.getLanguage().translate('sentLabel', 'labels', 'RealEstateRequest'),
                    asOfLabel: this.getLanguage().translate('asOfLabel', 'labels', 'RealEstateRequest'),
                    ruleLabel: this.getLanguage().translate('ruleVersionLabel', 'labels', 'RealEstateRequest'),
                    choGia: this.getLanguage().translate('choGia', 'labels', 'RealEstateRequest'),
                    sentSnapshot: this.getLanguage().translate('sentSnapshot', 'labels', 'RealEstateRequest'),
                },
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.state = 'loading';
            this.rows = [];
            this.fetchRows();
        },

        fetchRows: function () {
            var self = this;
            var params = {
                'where[0][type]': 'equals',
                'where[0][attribute]': 'inquiryId',
                'where[0][value]': this.model.id,
                maxSize: 50,
                offset: 0,
            };

            Espo.Ajax.getRequest('NgShortlist', params).then(function (response) {
                var list = (response && response.list) || [];
                self.rows = list.map(function (row) {
                    return {
                        id: row.id,
                        propertyName: row.propertyName || row.id,
                        statusLabel: self.getLanguage().translateOption(row.status, 'status', 'NgShortlist') || row.status || '',
                        fitReason: row.fitReason || '',
                        status: row.status,
                        sentSnapshotId: row.sentSnapshotId || null,
                        snapshot: null,
                    };
                });
                self.state = self.rows.length ? 'ok' : 'empty';
                if (self.isRendered()) self.reRender();
                self.rows.forEach(function (row) { self.fetchSnapshot(row); });
            }).catch(function (error) {
                self.state = error && error.status === 403 ? 'denied' : 'error';
                if (self.isRendered()) self.reRender();
            });
        },

        fetchSnapshot: function (row) {
            if (!row.sentSnapshotId) return;
            var self = this;

            Espo.Ajax.getRequest('NgShortlistSnapshot/' + row.sentSnapshotId).then(function (snap) {
                if (!snap) return;
                row.snapshot = {
                    selectorStatus: snap.selectorStatus,
                    amount: snap.amount,
                    currency: snap.currency || '',
                    unit: self.getLanguage().translateOption(snap.unit, 'unit', 'NgShortlist') || snap.unit || '',
                    asOf: snap.asOf || '',
                    ruleVersion: snap.ruleVersion || '',
                    hasPrice: snap.selectorStatus === 'OK' && snap.amount != null,
                };
                if (self.isRendered()) self.reRender();
            }).catch(function () { /* deny/unknown -> no snapshot display, status remains */ });
        },

        actionRefresh: function () {
            this.state = 'loading';
            this.rows = [];
            this.fetchRows();
            if (this.isRendered()) this.reRender();
        },
    });
});