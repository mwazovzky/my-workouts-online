import { useLocaleStore } from '@/stores/locale';

/**
 * `t(key, replacements)` with Laravel-style `:placeholder` substitution,
 * backed by the client-owned catalogs in `src/i18n/locales`.
 */
export function useTranslation() {
  const store = useLocaleStore();

  return { t: (key, replacements = {}) => store.t(key, replacements) };
}
