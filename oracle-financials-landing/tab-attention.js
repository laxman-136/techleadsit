(() => {
  const originalTitle = document.title;
  const updateTitle = () => {
    document.title = document.visibilityState === 'hidden'
      ? '(1) Message for you | Tech Leads IT'
      : originalTitle;
  };
  document.addEventListener('visibilitychange', updateTitle);
  window.addEventListener('pageshow', updateTitle);
})();
