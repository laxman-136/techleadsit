/* Appearance is local to this browser; campaign values still use WordPress settings. */
const settingsRoot = document.querySelector('.tl-settings');
const settingsForm = document.getElementById('techleadsitForm');
let settingsDirty = false;
function setSettingsTheme(theme) {
    settingsRoot.dataset.theme = theme;
    settingsRoot.querySelectorAll('[data-set-theme]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.setTheme === theme)));
    try { localStorage.setItem('tlit-settings-theme', theme); } catch (_) {}
}
let initialTheme = 'dark';
try { const saved = localStorage.getItem('tlit-settings-theme'); if (saved === 'light' || saved === 'dark') initialTheme = saved; } catch (_) {}
setSettingsTheme(initialTheme);
settingsRoot.querySelectorAll('[data-set-theme]').forEach(button => button.addEventListener('click', () => setSettingsTheme(button.dataset.setTheme)));
function formChanged(message) {
    settingsDirty = true;
    document.getElementById('tl-feedback').textContent = typeof message === 'string' ? message : 'Unsaved changes. Save to update the connected landing pages.';
}
settingsForm.addEventListener('input', formChanged);
settingsForm.addEventListener('change', formChanged);
settingsForm.addEventListener('submit', () => { settingsDirty = false; document.getElementById('tl-feedback').textContent = 'Saving changes…'; });
window.addEventListener('beforeunload', event => { if (settingsDirty) { event.preventDefault(); event.returnValue = ''; } });
function restoreCampaignSettings() {
    settingsForm.reset();
    settingsForm.querySelectorAll('.otp-slug-checkbox').forEach(handleOtpSlugToggle);
    settingsDirty = false;
    document.getElementById('tl-feedback').textContent = 'Saved values restored. No pending changes.';
}
