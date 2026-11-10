(() => {
  const content = document.querySelector('#lp-live-content');
  const toggle = document.querySelector('#lp-live-toggle');
  const status = document.querySelector('#lp-live-status');
  if (!content || !toggle || !status) return;
  let paused = false;
  let busy = false;
  let filtersDirty = false;
  content.addEventListener('input', event => {
    if (event.target.closest('.lp-filters')) filtersDirty = true;
  });
  content.addEventListener('change', event => {
    if (event.target.closest('.lp-filters')) filtersDirty = true;
  });
  toggle.addEventListener('click', () => {
    paused = !paused;
    toggle.setAttribute('aria-pressed', String(!paused));
    toggle.textContent = paused ? 'Resume live updates' : 'Pause live updates';
    status.textContent = paused ? 'Live updates paused.' : 'Live updates resumed.';
    if (!paused) refresh();
  });
  async function refresh() {
    if (paused || busy || document.hidden) return;
    if (filtersDirty || content.contains(document.activeElement)) {
      status.textContent = filtersDirty ? 'Apply filters to resume live updates.' : 'Updates wait while you interact with this panel.';
      return;
    }
    busy = true;
    try {
      // Reuse the authenticated, capability-checked admin page and its current filters.
      const response = await fetch(location.href, {
        credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(12000)
      });
      if (!response.ok) throw new Error('Refresh failed');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const next = page.querySelector('#lp-live-content');
      if (!next) throw new Error('Sign-in or access changed');
      if (paused || document.hidden || filtersDirty || content.contains(document.activeElement)) return;
      next.querySelectorAll('script').forEach(script => script.remove());
      content.replaceChildren(...next.childNodes);
      status.textContent = 'Updated ' + new Date().toLocaleTimeString() + ' · refreshes every 5 seconds';
    } catch {
      status.textContent = 'Update unavailable. Retrying automatically; refresh or sign in if it continues.';
    } finally {
      busy = false;
    }
  }
  setInterval(refresh, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
