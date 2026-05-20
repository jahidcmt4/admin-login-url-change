;(function ($) {
    'use strict';

    $(document).ready(function () {

        /* ── Tab Navigation ─────────────────────────────────── */
        $('.aluc-tab-btn').on('click', function (e) {
            e.preventDefault();
            var target = $(this).data('tab');

            // Ignore pro tabs (locked)
            if ($(this).data('pro')) {
                return;
            }

            $('.aluc-tab-btn').removeClass('aluc-active');
            $('.aluc-tab-panel').removeClass('aluc-active');
            $(this).addClass('aluc-active');
            $('#aluc-panel-' + target).addClass('aluc-active');

            // Persist active tab in sessionStorage
            if (window.sessionStorage) {
                sessionStorage.setItem('aluc_active_tab', target);
            }
        });

        // Restore last active tab
        var lastTab = window.sessionStorage && sessionStorage.getItem('aluc_active_tab');
        if (lastTab) {
            var $btn = $('[data-tab="' + lastTab + '"]').not('[data-pro]');
            if ($btn.length) {
                $btn.trigger('click');
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
            $btn.find('.aluc-btn-text').text('Saving…');

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
                        // Update the current-URL display
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

            var total = parseFloat($fill.data('total')) || 283; // 2*π*45
            var pct   = parseFloat($fill.data('pct'))   || 0;
            var offset = total - (total * pct / 100);
            $fill.css('stroke-dasharray', total);
            $fill.css('stroke-dashoffset', total); // start at 0
            setTimeout(function () {
                $fill.css('stroke-dashoffset', offset);
            }, 200);
        }

        /* ── Toggle: basic feedback (free toggles) ────────────── */
        $('.aluc-free-toggle').on('change', function () {
            var key   = $(this).data('option');
            var value = $(this).is(':checked') ? 1 : 0;

            $.ajax({
                url:  aluc_core.ajax_url,
                type: 'POST',
                data: {
                    action: 'aluc_save_option',
                    option_key:   key,
                    option_value: value,
                    _nonce:       aluc_core.nonce
                }
            });
        });

        /* ── Pro tab click → show modal hint ─────────────────── */
        $('.aluc-tab-btn[data-pro]').on('click', function () {
            // Scroll to upgrade card
            var $card = $('.aluc-upgrade-card');
            if ($card.length) {
                $('html, body').animate({ scrollTop: $card.offset().top - 40 }, 400);
                $card.css({ outline: '2px solid #f59e0b', borderRadius: '16px' });
                setTimeout(function () { $card.css('outline', ''); }, 1500);
            }
        });
    });
})(jQuery);