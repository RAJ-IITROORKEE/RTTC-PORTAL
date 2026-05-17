/**
 * RTTC 2026 - Main app JS
 */
(function ($) {
    'use strict';

    // Auto-dismiss flash messages after 5s
    setTimeout(function () {
        $('.alert-dismissible').fadeOut(500, function () { $(this).remove(); });
    }, 5000);

    // Form submit spinner overlay
    $('form').on('submit', function () {
        const btn = $(this).find('[type="submit"]');
        if (btn.length && !btn.data('no-spinner')) {
            btn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...'
            );
        }
    });

    // Phone number input: only digits
    $('input[type="tel"]').on('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Numeric-only inputs
    $('input[data-numeric]').on('input', function () {
        this.value = this.value.replace(/[^0-9.]/g, '');
    });

    // Auto-calculate percentage when total/obtained marks change
    $(document).on('input', '[data-total-marks], [data-obtained-marks]', function () {
        const prefix = $(this).data('prefix');
        if (!prefix) return;
        const total    = parseFloat($('[name="' + prefix + '_total_marks"]').val()) || 0;
        const obtained = parseFloat($('[name="' + prefix + '_obtained_marks"]').val()) || 0;
        if (total > 0) {
            const pct = (obtained / total * 100).toFixed(2);
            $('[name="' + prefix + '_percentage"]').val(pct);
        }
    });

    // Back to top button
    $(window).on('scroll', function () {
        if ($(this).scrollTop() > 300) {
            $('#backToTop').fadeIn(200);
        } else {
            $('#backToTop').fadeOut(200);
        }
    });

    $('#backToTop').on('click', function () {
        $('html, body').animate({ scrollTop: 0 }, 400);
    });

})(jQuery);
