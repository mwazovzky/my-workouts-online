import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Composable for accessing translations from the backend.
 *
 * Translations are shared via Inertia props from HandleInertiaRequests middleware.
 * Uses Laravel JSON translation files (lang/*.json) as the single source of truth.
 *
 * @returns {{ t: (key: string, replacements?: Record<string, string>) => string }}
 */
export function useTranslation() {
  const page = usePage();
  const translations = computed(() => page.props.translations || {});

  /**
   * Translate a given key, with optional placeholder replacements.
   *
   * Placeholders use Laravel's `:placeholder` syntax.
   * Falls back to the key itself if no translation is found.
   *
   * @param {string} key - The translation key
   * @param {Record<string, string>} [replacements={}] - Key-value pairs for placeholder replacement
   * @returns {string} The translated string
   */
  function t(key, replacements = {}) {
    let translation = translations.value[key] || key;

    for (const [placeholder, value] of Object.entries(replacements)) {
      translation = translation.replace(`:${placeholder}`, value);
    }

    return translation;
  }

  return { t };
}
