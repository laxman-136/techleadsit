(function () {
    'use strict';

    function init() {
        initThemeToggle();
        initTabBars();
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
                try { document.cookie = storageKey + '=' + nextTheme + ';path=/;max-age=31536000;SameSite=Lax'; } catch (error) {}
                root.classList.add('is-theme-switching');
                apply(nextTheme);
                window.setTimeout(function () { root.classList.remove('is-theme-switching'); }, 420);
            });
        });
    }

    function initTabBars() {
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        Array.prototype.forEach.call(document.querySelectorAll('[data-tlit-tabbar]'), function (bar) {
            var links = Array.prototype.slice.call(bar.querySelectorAll('a[href]'));
            var indicator = bar.querySelector('[data-tlit-tab-indicator]');
            if (!links.length || !indicator || !bar.querySelector('a[aria-current="page"]')) return;
            var suppressClick = false;
            var drag = null;
            var state = 'idle';
            var navigationTimer = 0;

            function committed() {
                return bar.querySelector('a[aria-current="page"]');
            }
            function metrics(link) {
                var barBox = bar.getBoundingClientRect();
                var linkBox = link.getBoundingClientRect();
                return { left: linkBox.left - barBox.left + bar.scrollLeft, width: linkBox.width };
            }
            function place(link, immediate) {
                if (!link) return;
                var box = metrics(link);
                bar.classList.toggle('is-positioning', !!immediate);
                indicator.style.width = box.width + 'px';
                indicator.style.transform = 'translate3d(' + box.left + 'px,0,0)';
                if (immediate) window.requestAnimationFrame(function () { bar.classList.remove('is-positioning'); });
            }
            function nearest(left, width) {
                var centre = left + width / 2;
                return links.reduce(function (best, link) {
                    var distance = Math.abs(centre - (link.offsetLeft + link.offsetWidth / 2));
                    return !best || distance < best.distance ? { link: link, distance: distance } : best;
                }, null).link;
            }
            function clearTarget() {
                links.forEach(function (link) { link.removeAttribute('data-tlit-drop-target'); });
            }
            function showTarget(link) {
                clearTarget();
                if (link && link !== committed()) link.setAttribute('data-tlit-drop-target', 'true');
            }
            function navigate(link) {
                var current = committed();
                if (!link || link === current || state === 'navigating') {
                    state = 'idle';
                    clearTarget();
                    place(current, false);
                    return;
                }
                state = 'navigating';
                bar.classList.add('is-navigating');
                showTarget(link);
                place(link, false);
                window.clearTimeout(navigationTimer);
                navigationTimer = window.setTimeout(function () { window.location.assign(link.href); }, reducedMotion ? 0 : 180);
            }
            function resetDrag(cancelled) {
                if (!drag) return false;
                var moved = drag.moved;
                var target = cancelled ? committed() : drag.target;
                var pointerId = drag.pointerId;
                drag = null;
                state = 'idle';
                bar.classList.remove('is-dragging');
                clearTarget();
                var current = committed();
                if (current && current.hasPointerCapture && current.hasPointerCapture(pointerId)) current.releasePointerCapture(pointerId);
                if (moved) {
                    suppressClick = true;
                    window.setTimeout(function () { suppressClick = false; }, 350);
                    navigate(target);
                } else {
                    place(current, false);
                }
                return moved;
            }

            place(committed(), true);
            bar.classList.add('is-ready');

            links.forEach(function (link) {
                link.addEventListener('click', function (event) {
                    if (suppressClick) { event.preventDefault(); return; }
                    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    if (link === committed() || state !== 'idle') { event.preventDefault(); return; }
                    event.preventDefault();
                    navigate(link);
                });
            });

            bar.addEventListener('pointerdown', function (event) {
                var current = committed();
                if (event.button !== 0 || reducedMotion || state !== 'idle' || event.target.closest('a') !== current) return;
                var box = metrics(current);
                drag = { pointerId: event.pointerId, startX: event.clientX, left: box.left, width: box.width, moved: false, target: current };
                state = 'pressed';
                current.setPointerCapture(event.pointerId);
            });
            bar.addEventListener('dragstart', function (event) { if (event.target.closest('a') === committed()) event.preventDefault(); });
            bar.addEventListener('pointermove', function (event) {
                if (!drag || drag.pointerId !== event.pointerId) return;
                var delta = event.clientX - drag.startX;
                if (!drag.moved && Math.abs(delta) < 9) return;
                drag.moved = true;
                state = 'dragging';
                event.preventDefault();
                bar.classList.add('is-dragging');
                var edge = Math.max(5, bar.scrollWidth - drag.width - 5);
                var left = Math.min(edge, Math.max(5, drag.left + delta));
                indicator.style.transform = 'translate3d(' + left + 'px,0,0)';
                drag.target = nearest(left, drag.width);
                showTarget(drag.target);
            });
            function finish(event, cancelled) {
                if (!drag || drag.pointerId !== event.pointerId) return;
                resetDrag(cancelled);
            }
            bar.addEventListener('pointerup', function (event) { finish(event, false); });
            bar.addEventListener('pointercancel', function (event) { finish(event, true); });
            bar.addEventListener('lostpointercapture', function () { if (drag) resetDrag(true); });
            window.addEventListener('blur', function () { if (drag) resetDrag(true); });
            bar.addEventListener('keydown', function (event) { if (event.key === 'Escape' && drag) resetDrag(true); });
            bar.addEventListener('scroll', function () { if (state === 'idle') place(committed(), true); }, { passive: true });

            if ('ResizeObserver' in window) {
                new ResizeObserver(function () { if (state === 'idle') place(committed(), true); }).observe(bar);
            } else {
                window.addEventListener('resize', function () { if (state === 'idle') place(committed(), true); });
            }
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { if (state === 'idle') place(committed(), true); });
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
