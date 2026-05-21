;(function ($) {
    'use strict';

    $(document).ready(function () {

        /* ── Tab Navigation ─────────────────────────────────── */
        $('.aluc-tab-btn').on('click', function (e) {
            e.preventDefault();
            var target = $(this).data('tab');
            if (!target) return;

            $('.aluc-tab-btn').removeClass('aluc-active');
            $('.aluc-tab-panel').removeClass('aluc-active');
            $(this).addClass('aluc-active');
            $('#aluc-panel-' + target).addClass('aluc-active');

            // Persist active tab in sessionStorage
            if (window.sessionStorage) {
                sessionStorage.setItem('aluc_active_tab', target);
            }
        });

        // Restore last active tab on page load
        var lastTab = window.sessionStorage && sessionStorage.getItem('aluc_active_tab');
        if (lastTab) {
            var $restore = $('[data-tab="' + lastTab + '"]');
            if ($restore.length) {
                $restore.trigger('click');
            }
        }

        /* ── Save Slug via AJAX ──────────────────────────────── */
        $('#aluc-save-btn').on('click', function (e) {
            e.preventDefault();
            var $btn  = $(this);
            var slug  = $('#aluc-new-login-url').val().trim();
            var $ok   = $('#aluc-notice-success');
            var $err  = $('#aluc-notice-error');

            $ok.removeClass('show');
            $err.removeClass('show');

            if (!slug) {
                $err.find('.aluc-notice-msg').text('Please enter a login slug.');
                $err.addClass('show');
                return;
            }

            $btn.addClass('loading').prop('disabled', true);
            $btn.find('.aluc-btn-text').text('Saving\u2026');

            $.ajax({
                url:  aluc_core.ajax_url,
                type: 'POST',
                data: {
                    action: 'aluc_save_slug',
                    slug:   slug,
                    _nonce: aluc_core.nonce
                },
                success: function (res) {
                    if (res.success) {
                        $ok.find('.aluc-notice-msg').text(res.data.message);
                        $ok.addClass('show');
                        var base = window.location.origin + '/';
                        $('#aluc-current-url-display').text(base + res.data.slug + '/');
                        setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                    } else {
                        $err.find('.aluc-notice-msg').text(res.data.message);
                        $err.addClass('show');
                        $btn.removeClass('loading').prop('disabled', false);
                        $btn.find('.aluc-btn-text').text('Save Changes');
                        setTimeout(function () { $err.removeClass('show'); }, 3500);
                    }
                },
                error: function () {
                    $err.find('.aluc-notice-msg').text('Something went wrong. Please try again.');
                    $err.addClass('show');
                    $btn.removeClass('loading').prop('disabled', false);
                    $btn.find('.aluc-btn-text').text('Save Changes');
                }
            });
        });

        /* ── Security Score Animation ────────────────────────── */
        animateScore();

        function animateScore() {
            var $fill = $('#aluc-score-fill');
            if (!$fill.length) return;

            var total  = parseFloat($fill.data('total')) || 283;
            var pct    = parseFloat($fill.data('pct'))   || 0;
            var offset = total - (total * pct / 100);
            $fill.css('stroke-dasharray', total);
            $fill.css('stroke-dashoffset', total);
            setTimeout(function () {
                $fill.css('stroke-dashoffset', offset);
            }, 200);
        }

        /* ── Toggle: free option toggles ─────────────────────── */
        $('.aluc-free-toggle').on('change', function () {
            var key   = $(this).data('option');
            var value = $(this).is(':checked') ? 1 : 0;

            $.ajax({
                url:  aluc_core.ajax_url,
                type: 'POST',
                data: {
                    action:       'aluc_save_option',
                    option_key:   key,
                    option_value: value,
                    _nonce:       aluc_core.nonce
                }
            });
        });

        /* ── Footer Upgrade Banner Dismiss ───────────────────── */
        var $banner = $('#aluc-footer-upgrade-banner');
        if ($banner.length) {
            // Hide if already dismissed this session
            if (window.sessionStorage && sessionStorage.getItem('aluc_banner_dismissed')) {
                $banner.hide();
            }

            $('#aluc-banner-dismiss').on('click', function () {
                $banner.slideUp(300);
                if (window.sessionStorage) {
                    sessionStorage.setItem('aluc_banner_dismissed', '1');
                }
            });
        }

    });
})(jQuery);