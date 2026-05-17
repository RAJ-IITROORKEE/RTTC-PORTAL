/**
 * RTTC 2026 - Admin JS
 */
(function ($) {
    'use strict';

    // Sidebar toggle (mobile)
    $('#sidebarToggle').on('click', function () {
        $('#adminSidebar').toggleClass('show collapsed');
        $('#adminMain').toggleClass('expanded');
    });

    // DataTable enhancements (basic sorting)
    $('[data-sortable]').each(function () {
        // simple click-to-sort on th
        $(this).find('thead th').on('click', function () {
            const idx   = $(this).index();
            const tbody = $(this).closest('table').find('tbody');
            const rows  = tbody.find('tr').toArray();
            const asc   = $(this).data('sort') !== 'asc';
            $(this).data('sort', asc ? 'asc' : 'desc');
            rows.sort(function (a, b) {
                const A = $(a).find('td').eq(idx).text().trim().toLowerCase();
                const B = $(b).find('td').eq(idx).text().trim().toLowerCase();
                return asc ? A.localeCompare(B) : B.localeCompare(A);
            });
            tbody.html('');
            rows.forEach(function (r) { tbody.append(r); });
        });
    });

    // Confirm delete buttons
    $('[data-confirm]').on('click', function (e) {
        if (!confirm($(this).data('confirm') || 'Are you sure?')) {
            e.preventDefault();
        }
    });

    // Auto-dismiss alerts
    setTimeout(function () {
        $('.alert-dismissible').fadeOut(600, function () { $(this).remove(); });
    }, 5000);

})(jQuery);
