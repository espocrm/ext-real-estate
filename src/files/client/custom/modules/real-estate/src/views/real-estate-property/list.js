define('real-estate:views/real-estate-property/list', ['views/list'], function (Dep) {
    return Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.applyAccessibilityLabels();
        },
        applyAccessibilityLabels: function () {
            this.$el.find('input.form-control.text-filter').attr('aria-label', 'Tìm kiếm bất động sản');
            this.$el.find('.select-all.form-checkbox').attr('aria-label', 'Chọn tất cả bản ghi');
            this.$el.find('input.record-checkbox.form-checkbox').attr('aria-label', 'Chọn bản ghi');
            this.$el.find('button.dropdown-toggle').each(function () {
                var $button = $(this);
                if (!$button.attr('aria-label')) {
                    var text = $button.text().trim();
                    $button.attr('aria-label', text || 'Menu hành động');
                }
            });
        }
    });
});
