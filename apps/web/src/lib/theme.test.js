import { describe, expect, it, vi, beforeEach } from 'vitest';

function stubMatchMedia(matches) {
  const listeners = [];
  const query = {
    matches,
    addEventListener: (_, fn) => listeners.push(fn),
    removeEventListener: vi.fn(),
  };
  vi.stubGlobal(
    'matchMedia',
    vi.fn(() => query)
  );
  return { query, fire: () => listeners.forEach(fn => fn()) };
}

async function load(matches) {
  vi.resetModules();
  const media = stubMatchMedia(matches);
  const mod = await import('@/lib/theme');
  return { ...mod, media };
}

describe('theme', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.className = '';
    delete document.documentElement.dataset.themePreference;
  });

  it('applies an explicit preference and persists it', async () => {
    const { applyThemePreference, currentThemePreference } = await load(false);

    applyThemePreference('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(currentThemePreference()).toBe('dark');
    expect(window.localStorage.getItem('theme-preference')).toBe('dark');

    applyThemePreference('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('follows the OS in system mode and reacts to changes', async () => {
    const { applyThemePreference, media } = await load(true);

    applyThemePreference('system');
    expect(document.documentElement.classList.contains('dark')).toBe(true);

    media.query.matches = false;
    media.fire();
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('ignores OS changes when an explicit preference is set', async () => {
    const { applyThemePreference, media } = await load(false);

    applyThemePreference('light');
    media.query.matches = true;
    media.fire();

    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('defaults to system when called without a preference', async () => {
    const { applyThemePreference, currentThemePreference } = await load(false);

    applyThemePreference();
    expect(currentThemePreference()).toBe('system');
  });
});
