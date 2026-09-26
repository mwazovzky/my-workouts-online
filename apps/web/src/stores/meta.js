import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@/lib/api';

export const useMetaStore = defineStore('meta', () => {
  const availableLocales = ref({ en: 'English' });
  const themePreferences = ref(['light', 'dark', 'system']);
  const loaded = ref(false);

  async function load() {
    if (loaded.value) {
      return;
    }
    const { data } = await api.get('/meta');
    availableLocales.value = data.data.available_locales;
    themePreferences.value = data.data.theme_preferences;
    loaded.value = true;
  }

  return { availableLocales, themePreferences, loaded, load };
});
