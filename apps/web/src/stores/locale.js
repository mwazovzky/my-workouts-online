import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import en from '@/i18n/locales/en.json';
import ru from '@/i18n/locales/ru.json';

const STORAGE_KEY = 'locale';
const messages = { en, ru };

export const SUPPORTED_LOCALES = Object.keys(messages);

/**
 * The language the visitor chose (switcher or profile), or null. Always null
 * when prerendering, where there is no browser.
 */
export function storedLocale() {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    return SUPPORTED_LOCALES.includes(stored) ? stored : null;
  } catch {
    return null;
  }
}

function detectLocale() {
  const stored = storedLocale();
  if (stored) {
    return stored;
  }

  const browser = (globalThis.navigator?.language || 'en').slice(0, 2);
  return SUPPORTED_LOCALES.includes(browser) ? browser : 'en';
}

/**
 * UI strings live in the client. The active locale is what we render and what
 * we send as Accept-Language; the profile locale is only a synced preference.
 */
export const useLocaleStore = defineStore('locale', () => {
  const locale = ref(detectLocale());

  const translations = computed(() => messages[locale.value] ?? {});

  /**
   * `persist: false` switches the language without recording it as the
   * visitor's choice, as when a public page's URL decides it.
   */
  function setLocale(value, { persist = true } = {}) {
    if (!SUPPORTED_LOCALES.includes(value)) {
      return;
    }
    locale.value = value;
    if (persist) {
      try {
        window.localStorage.setItem(STORAGE_KEY, value);
      } catch {
        /* storage unavailable */
      }
    }
    if (typeof document !== 'undefined') {
      document.documentElement.lang = value;
    }
  }

  function t(key, replacements = {}) {
    let translation = translations.value[key] || key;

    for (const [placeholder, value] of Object.entries(replacements)) {
      translation = translation.replaceAll(`:${placeholder}`, String(value));
    }

    return translation;
  }

  return { locale, translations, setLocale, t };
});
