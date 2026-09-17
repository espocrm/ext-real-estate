Espo.define('real-estate:views/real-estate-request/list', 'views/list', function (Dep) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.once('after:render', function () {
                this.startA11y();
            });
        },

        startA11y: function () {
            this.applyA11y();
            if (this.a11yObserver) {
                this.a11yObserver.disconnect();
            }
            var self = this;
            if (typeof MutationObserver !== 'undefined') {
                this.a11yObserver = new MutationObserver(function () {
                    self.applyA11y();
                });
                this.a11yObserver.observe(this.el, {childList: true, subtree: true});
                setTimeout(function () {
                    if (self.a11yObserver) {
                        self.a11yObserver.disconnect();
                        self.a11yObserver = null;
                    }
                    self.applyA11y();
                }, 5000);
            }
        },

        applyA11y: function () {
            var $el = this.$el;
            if (!$el || !$el.length) {
                return;
            }
            $el.find('input.form-control.text-filter').attr('aria-label', 'Tìm kiếm nhu cầu');
            $el.find('.select-all.form-checkbox').attr('aria-label', 'Chọn tất cả bản ghi');
            $el.find('input.record-checkbox.form-checkbox').each(function () {
                if (!$(this).attr('aria-label')) {
                    $(this).attr('aria-label', 'Chọn bản ghi');
                }
            });
            $el.find('button').each(function () {
                var $b = $(this);
                if ($b.attr('aria-label')) {
                    return;
                }
                var text = ($b.text() || '').trim();
                if (text) {
                    $b.attr('aria-label', text);
                    return;
                }
                if ($b.hasClass('btn-icon-wide') || $b.hasClass('add-filter')) {
                    $b.attr('aria-label', 'Thêm bộ lọc');
                } else if ($b.hasClass('dropdown-toggle')) {
                    $b.attr('aria-label', 'Menu hành động');
                } else {
                    $b.attr('aria-label', 'Hành động');
                }
            });
            $el.find('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea').each(function () {
                var $i = $(this);
                if ($i.attr('aria-label') || $i.attr('placeholder')) {
                    return;
                }
                var $cell = $i.closest('.cell');
                var text = $cell.find('label').first().text().trim();
                if (!text) {
                    var $parent = $i.parent();
                    text = $parent.find('label').first().text().trim();
                }
                if (text) {
                    $i.attr('aria-label', text);
                }
            });
        }
    });
});
