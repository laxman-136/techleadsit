(function () {
    'use strict';

    function init() {
        initThemeToggle();
        var root = document.querySelector('.tlit-cb');
        if (!root) return;
        initDashboardFilters(root);
        initDashboardDisclosures(root);
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

    function initThemeToggle() {
        var roots = Array.prototype.slice.call(document.querySelectorAll('[data-tlit-theme-root]'));
        if (!roots.length) return;
        var storageKey = 'tlit_bridge_theme';
        var theme = 'dark';
        try {
            if (window.localStorage.getItem(storageKey) === 'light') theme = 'light';
        } catch (error) {}

        function apply(nextTheme) {
            theme = nextTheme === 'light' ? 'light' : 'dark';
            roots.forEach(function (root) {
                root.classList.toggle('is-light', theme === 'light');
                root.setAttribute('data-theme', theme);
                var button = root.querySelector('[data-tlit-theme-toggle]');
                if (!button) return;
                var light = theme === 'light';
                var label = light ? 'Switch to dark theme' : 'Switch to light theme';
                button.setAttribute('aria-pressed', light ? 'true' : 'false');
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
            });
        }

        apply(theme);
        roots.forEach(function (root) {
            var button = root.querySelector('[data-tlit-theme-toggle]');
            if (!button) return;
            button.addEventListener('click', function () {
                var nextTheme = theme === 'light' ? 'dark' : 'light';
                try { window.localStorage.setItem(storageKey, nextTheme); } catch (error) {}
                apply(nextTheme);
            });
        });
    }

    function initDashboardFilters(root) {
        var toggle = root.querySelector('.cb-dash-filter-toggle');
        var drawer = root.querySelector('.cb-dash-filter-drawer');
        if (!toggle || !drawer) return;
        var close = drawer.querySelector('.cb-dash-filter-close');

        function setOpen(open, moveFocus) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
            drawer.classList.toggle('is-open', open);
            if ('inert' in drawer) drawer.inert = !open;
            if (moveFocus) {
                var destination = open ? drawer.querySelector('select,input,button') : toggle;
                if (destination) destination.focus({ preventScroll: true });
            }
        }

        setOpen(false, false);
        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true', false);
        });
        if (close) close.addEventListener('click', function () { setOpen(false, true); });
        drawer.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setOpen(false, true);
        });
    }

    function initDashboardDisclosures(root) {
        Array.prototype.forEach.call(root.querySelectorAll('.cb-dash-disclosure'), function (details) {
            var summary = details.querySelector(':scope > summary');
            if (!summary) return;
            summary.addEventListener('click', function () {
                details.classList.add('is-moving');
                window.setTimeout(function () { details.classList.remove('is-moving'); }, 340);
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
