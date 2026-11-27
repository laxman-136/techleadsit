(function () {
    'use strict';
    function init() {
        const root = document.querySelector('.lp-workspace');
        const form = root && root.querySelector('[data-lp-review-form]');
        const dialog = root && root.querySelector('[data-lp-sync-dialog]');
        if (!form || !dialog || !window.fetch || !window.FormData) return;
        const title = dialog.querySelector('[data-lp-sync-title]');
        const description = dialog.querySelector('[data-lp-sync-description]');
        const close = dialog.querySelector('[data-lp-sync-close]');
        const status = form.querySelector('[name="lead_status"]');
        const note = form.querySelector('[name="note"]');
        const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
        let busy = false;
        let returnFocus = null;
        function show(phase, heading, message) {
            dialog.className = 'lp-sync-dialog is-' + phase;
            title.textContent = heading;
            description.textContent = message;
            close.hidden = busy;
            close.textContent = phase === 'synced' || phase === 'local' ? 'Done' : 'Back to review';
            dialog.hidden = false;
            if (busy) dialog.focus(); else close.focus();
        }
        function dismiss() {
            if (busy) return;
            dialog.hidden = true;
            if (returnFocus && returnFocus.isConnected) returnFocus.focus();
        }
        close.addEventListener('click', dismiss);
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); dismiss(); }
            if (event.key === 'Tab') { event.preventDefault(); (busy ? dialog : close).focus(); }
        });
        note.addEventListener('input', function () { note.setCustomValidity(''); });
        buttons.forEach(button => button.addEventListener('click', function () { note.setCustomValidity(''); }));
        window.addEventListener('pageshow', function () {
            if (!busy) { buttons.forEach(button => { button.disabled = false; }); form.removeAttribute('aria-busy'); }
        });
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (busy) return;
            const submitter = event.submitter || buttons[0];
            const notesOnly = submitter && submitter.name === 'sync_call_notes';
            if (notesOnly && !note.value.trim()) {
                note.setCustomValidity('Enter call notes before syncing Remarks.'); note.reportValidity(); return;
            }
            if (!form.reportValidity()) return;
            // Capture the clicked button before disabling it, so a notes-only action cannot become a status change.
            const payload = new FormData(form);
            payload.set('action', 'tlit_lp_review');
            if (notesOnly) payload.set('sync_call_notes', '1'); else payload.delete('sync_call_notes');
            const targetStatus = notesOnly ? status.dataset.savedStatus : status.value;
            const local = targetStatus === 'new' || targetStatus === 'junk';
            busy = true; returnFocus = submitter;
            buttons.forEach(button => { button.disabled = true; });
            form.setAttribute('aria-busy', 'true');
            show(local ? 'saving' : 'syncing', local ? 'Saving your call notes' : 'Syncing with TeleCRM',
                local ? 'Keeping this review safely in Bridge.' : 'Saving your review and updating the same lead.');
            let response;
            try {
                response = await fetch(form.dataset.ajaxUrl, {method:'POST',body:payload,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
                const result = await response.json();
                busy = false;
                if (result.success !== true) {
                    show('failed', 'Could not save this review', result.data && result.data.message ? result.data.message : 'Refresh the page before trying again.');
                    return;
                }
                const data = result.data;
                const phases = ['synced','local','failed','uncertain','sending','pending'];
                if (!data || !phases.includes(data.state) || !data.lead_status || typeof data.note !== 'string') throw new Error('Unconfirmed response');
                status.value = data.lead_status;
                status.dataset.savedStatus = data.lead_status;
                note.value = data.note;
                const notesButton = form.querySelector('[name="sync_call_notes"]');
                const held = data.lead_status === 'new' || data.lead_status === 'junk';
                notesButton.textContent = held ? 'Save call notes' : 'Save call notes & sync Remarks';
                form.querySelector('#lp-notes-help').textContent = held ? 'New and Junk Lead stay in WordPress. Call notes are saved locally; choose another qualification and save the review to sync them to TeleCRM Remarks.' : 'Call notes appear first in TeleCRM Remarks. The call-notes button keeps the saved lead qualification.';
                const panel = root.querySelector('.lp-sync-panel');
                const badge = panel.querySelector('.lp-badge');
                badge.textContent = data.crm_label;
                badge.className = 'lp-badge lp-badge--' + data.badge_status;
                panel.querySelector('[data-lp-sync-message]').textContent = data.message;
                const updated = panel.querySelector('[data-lp-sync-updated]');
                updated.hidden = !data.updated_label; updated.textContent = 'Last update: ' + (data.updated_label || '');
                const target = panel.querySelector('[data-lp-target-status]');
                target.hidden = !data.telecrm_status; target.querySelector('strong').textContent = data.telecrm_status || '';
                const refresh = panel.querySelector('[data-lp-sync-refresh]');
                if (refresh) refresh.hidden = data.state !== 'uncertain' && data.state !== 'sending';
                show(data.state, data.state === 'synced' ? 'Synced to TeleCRM' : data.state === 'local' ? 'Saved in Bridge' : data.crm_label, data.message);
            } catch (error) {
                busy = false;
                const message = 'Keep your notes here. Refresh and check the latest sync status before saving again; the update may have reached TeleCRM.';
                const panel = root.querySelector('.lp-sync-panel');
                if (panel) {
                    const badge = panel.querySelector('.lp-badge');
                    badge.textContent = 'Update not confirmed'; badge.className = 'lp-badge lp-badge--uncertain';
                    panel.querySelector('[data-lp-sync-message]').textContent = message;
                    const refresh = panel.querySelector('[data-lp-sync-refresh]');
                    if (refresh) refresh.hidden = false;
                }
                show('uncertain', 'Update could not be confirmed', message);
            } finally {
                busy = false; form.removeAttribute('aria-busy'); buttons.forEach(button => { button.disabled = false; });
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
