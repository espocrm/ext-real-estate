define('real-estate:views/real-estate-request/record/list', ['views/record/list'], function (Dep) {
    return Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.applyAccessibilityLabels();
        },

        applyAccessibilityLabels: function () {
            this.$el.find('.select-all.form-checkbox').attr('aria-label', 'Chọn tất cả bản ghi');
            this.$el.find('input.record-checkbox.form-checkbox').attr('aria-label', 'Chọn bản ghi');
            this.$el.find('button.dropdown-toggle').each(function () {
                var $button = $(this);
                if (!$button.attr('aria-label')) {
                    $button.attr('aria-label', 'Menu hàng');
                }
            });
        }
    });
});
