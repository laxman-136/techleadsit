(() => {
  const originalTitle = document.title;
  const messageTitle = '(1) New message for you | Tech Leads IT';
  let timer = null;
  let showingMessage = false;
  const stop = () => {
    if (timer !== null) clearInterval(timer);
    timer = null;
    showingMessage = false;
    document.title = originalTitle;
  };
  const update = () => {
    stop();
    if (document.visibilityState !== 'hidden') return;
    showingMessage = true;
    document.title = messageTitle;
    timer = setInterval(() => {
      if (document.visibilityState !== 'hidden') { stop(); return; }
      showingMessage = !showingMessage;
      document.title = showingMessage ? messageTitle : originalTitle;
    }, 2000);
  };
  document.addEventListener('visibilitychange', update);
  window.addEventListener('pageshow', update);
  window.addEventListener('pagehide', stop);
})();
