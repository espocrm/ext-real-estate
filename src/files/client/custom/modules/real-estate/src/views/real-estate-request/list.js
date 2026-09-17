Espo.define('real-estate:views/real-estate-request/list', 'views/list', function (Dep) {
    return Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.getAcl().check('RealEstateRequest', 'read')) {
                this.applyAccessibility();
            }
        },
        applyAccessibility: function () {
            var self = this;
            this.$el.find('input.form-control.text-filter').attr('aria-label', 'Tìm kiếm nhu cầu');
            this.$el.find('.select-all.form-checkbox').attr('aria-label', 'Chọn tất cả');
            this.$el.find('.record-checkbox.form-checkbox').attr('aria-label', 'Chọn bản ghi');
            this.$el.find('button.dropdown-toggle').each(function () {
                var $b = $(this);
                if (!$b.attr('aria-label')) {
                    $b.attr('aria-label', 'Menu hành động');
                }
            });
            this.$el.find('button.btn-icon.dropdown-toggle').attr('aria-label', 'Lọc nâng cao');
        }
    });
});
