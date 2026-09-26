// Applies the stored theme before the app mounts so there is no flash.
// Kept as a separate file so a strict Content-Security-Policy can allow it.
(function () {
  var preference = 'system';
  try {
    preference = window.localStorage.getItem('theme-preference') || 'system';
  } catch (_) {
    /* storage unavailable */
  }
  var dark =
    preference === 'dark' ||
    (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
  document.documentElement.dataset.themePreference = preference;
})();
