const STORAGE_KEY = 'theme-preference';
// Absent when the public pages are prerendered, where there is no browser.
const query = globalThis.window?.matchMedia('(prefers-color-scheme: dark)');

export function applyThemePreference(preference = 'system') {
  const dark = preference === 'dark' || (preference === 'system' && Boolean(query?.matches));
  document.documentElement.classList.toggle('dark', dark);
  document.documentElement.dataset.themePreference = preference;
  try {
    window.localStorage.setItem(STORAGE_KEY, preference);
  } catch {
    /* storage unavailable */
  }
}

export function currentThemePreference() {
  return document.documentElement.dataset.themePreference || 'system';
}

query?.addEventListener('change', () => {
  if (currentThemePreference() === 'system') {
    applyThemePreference('system');
  }
});
