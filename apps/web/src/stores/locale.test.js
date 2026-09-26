import { describe, expect, it, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useLocaleStore } from '@/stores/locale';
import { useTranslation } from '@/composables/useTranslation';

describe('locale store and useTranslation', () => {
  beforeEach(() => {
    window.localStorage.clear();
    setActivePinia(createPinia());
  });

  it('defaults to English and translates with placeholder replacement', () => {
    const { t } = useTranslation();

    expect(useLocaleStore().locale).toBe('en');
    expect(t('Save')).toBe('Save');
    expect(t('Missing Key')).toBe('Missing Key');
    expect(t('Hello, :name!', { name: 'Alex' })).toBe('Hello, Alex!');
  });

  it('switches catalogs, persists the choice and updates the html lang', () => {
    const store = useLocaleStore();
    const { t } = useTranslation();

    store.setLocale('ru');

    expect(t('Save')).toBe('Сохранить');
    expect(window.localStorage.getItem('locale')).toBe('ru');
    expect(document.documentElement.lang).toBe('ru');
  });

  it('ignores unsupported locales and restores a stored one', () => {
    window.localStorage.setItem('locale', 'ru');
    setActivePinia(createPinia());
    const store = useLocaleStore();

    expect(store.locale).toBe('ru');

    store.setLocale('fr');
    expect(store.locale).toBe('ru');
  });
});
