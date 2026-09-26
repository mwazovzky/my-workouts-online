const STORAGE_KEY = 'theme-preference';
const query = window.matchMedia('(prefers-color-scheme: dark)');

export function applyThemePreference(preference = 'system') {
  const dark = preference === 'dark' || (preference === 'system' && query.matches);
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

query.addEventListener('change', () => {
  if (currentThemePreference() === 'system') {
    applyThemePreference('system');
  }
});
