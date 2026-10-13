(function () {
    'use strict';

    function init() {
        var root = document.querySelector('.tlit-cb');
        if (!root) return;
        var form = root.querySelector('.tlit-cb-selection-form');
        if (!form) return;
        var rows = Array.prototype.slice.call(form.querySelectorAll('[data-tlit-select-row]'));
        var all = form.querySelector('[data-tlit-select-all]');
        var count = form.querySelector('[data-tlit-selection-count]');
        var download = form.querySelector('[data-tlit-download-selected]');

        function updateSelection() {
            var selected = rows.filter(function (checkbox) { return checkbox.checked; }).length;
            rows.forEach(function (checkbox) {
                var row = checkbox.closest('tr');
                if (row) row.classList.toggle('is-selected', checkbox.checked);
            });
            if (count) count.textContent = String(selected);
            if (download) download.disabled = selected === 0 || selected > 200;
            if (all) {
                all.checked = rows.length > 0 && selected === rows.length;
                all.indeterminate = selected > 0 && selected < rows.length;
            }
        }

        if (all) {
            all.addEventListener('change', function () {
                rows.forEach(function (checkbox) { checkbox.checked = all.checked; });
                updateSelection();
            });
        }
        rows.forEach(function (checkbox) { checkbox.addEventListener('change', updateSelection); });
        form.addEventListener('submit', function (event) {
            var selected = rows.filter(function (checkbox) { return checkbox.checked; }).length;
            if (selected < 1 || selected > 200) {
                event.preventDefault();
                if (rows[0]) rows[0].focus();
            }
        });
        updateSelection();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
