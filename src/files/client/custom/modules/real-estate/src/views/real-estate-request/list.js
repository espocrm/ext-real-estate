Espo.define('real-estate:views/real-estate-request/list', 'views/list', function (Dep) {
    return Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.applyAccessibility();
        },
        applyAccessibility: function () {
            var self = this;
            var $search = this.$el.find('input.form-control.text-filter');
            if ($search.length) {
                $search.attr('aria-label', 'Tìm kiếm nhu cầu');
            }
            this.$el.find('input.record-checkbox.form-checkbox').each(function () {
                $(this).attr('aria-label', 'Chọn bản ghi');
            });
            this.$el.find('.select-all.form-checkbox').attr('aria-label', 'Chọn tất cả');
            this.$el.find('button.dropdown-toggle').each(function () {
                var $b = $(this);
                if (!$b.attr('aria-label')) {
                    $b.attr('aria-label', 'Menu hành động');
                }
            });
            this.$el.find('button.btn-icon.dropdown-toggle').attr('aria-label', 'Lọc nâng cao');
            // create/edit form fields without labels
            this.$el.find('input:not([type=hidden]):not([type=checkbox])').each(function () {
                var $i = $(this);
                if ($i.attr('aria-label') || $i.attr('placeholder')) return;
                var $row = $i.closest('.cell').find('label').first();
                var text = $row.text().trim();
                if (text) $i.attr('aria-label', text);
            });
            this.$el.find('textarea').each(function () {
                var $i = $(this);
                if ($i.attr('aria-label')) return;
                var $row = $i.closest('.cell').find('label').first();
                var text = $row.text().trim();
                if (text) $i.attr('aria-label', text);
            });
        }
    });
});
