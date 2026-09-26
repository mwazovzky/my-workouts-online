import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import en from '@/i18n/locales/en.json';
import ru from '@/i18n/locales/ru.json';

const STORAGE_KEY = 'locale';
const messages = { en, ru };

export const SUPPORTED_LOCALES = Object.keys(messages);

function detectLocale() {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored && SUPPORTED_LOCALES.includes(stored)) {
      return stored;
    }
  } catch {
    /* storage unavailable */
  }

  const browser = (navigator.language || 'en').slice(0, 2);
  return SUPPORTED_LOCALES.includes(browser) ? browser : 'en';
}

/**
 * UI strings live in the client. The active locale is what we render and what
 * we send as Accept-Language; the profile locale is only a synced preference.
 */
export const useLocaleStore = defineStore('locale', () => {
  const locale = ref(detectLocale());

  const translations = computed(() => messages[locale.value] ?? {});

  function setLocale(value) {
    if (!SUPPORTED_LOCALES.includes(value)) {
      return;
    }
    locale.value = value;
    try {
      window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
      /* storage unavailable */
    }
    document.documentElement.lang = value;
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
