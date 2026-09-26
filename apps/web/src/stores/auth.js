import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api, configureApiAuth } from '@/lib/api';
import { applyThemePreference } from '@/lib/theme';
import { useLocaleStore } from '@/stores/locale';

/**
 * Access token lives only in memory. The refresh token is an httpOnly cookie
 * the browser sends to /auth/refresh, so a page load starts with a silent
 * refresh to rebuild the session.
 */
export const useAuthStore = defineStore('auth', () => {
  const accessToken = ref(null);
  const user = ref(null);
  const ready = ref(false);
  let bootstrapPromise = null;

  const isAuthenticated = computed(() => user.value !== null);
  const isVerified = computed(() => Boolean(user.value?.email_verified));
  const isAdmin = computed(() => Boolean(user.value?.is_admin));

  function applySession(payload) {
    accessToken.value = payload.access_token;
    setUser(payload.user);
  }

  function setUser(value) {
    user.value = value;
    if (value?.theme_preference) {
      applyThemePreference(value.theme_preference);
    }
    if (value?.locale) {
      useLocaleStore().setLocale(value.locale);
    }
  }

  function clear() {
    accessToken.value = null;
    user.value = null;
  }

  async function refresh() {
    try {
      const { data } = await api.post('/auth/refresh');
      applySession(data);
      return true;
    } catch {
      clear();
      return false;
    }
  }

  async function fetchUser() {
    const { data } = await api.get('/me');
    setUser(data.data);
    return user.value;
  }

  /**
   * Runs once per page load: silent refresh, then /me. Resolves either way.
   */
  function bootstrap() {
    if (!bootstrapPromise) {
      bootstrapPromise = (async () => {
        if (await refresh()) {
          try {
            await fetchUser();
          } catch {
            clear();
          }
        }
        ready.value = true;
      })();
    }
    return bootstrapPromise;
  }

  async function login(credentials) {
    const { data } = await api.post('/auth/login', { ...credentials, client: 'web' });
    applySession(data);
    return user.value;
  }

  async function register(payload) {
    const { data } = await api.post('/auth/register', { ...payload, client: 'web' });
    applySession(data);
    return user.value;
  }

  async function logout() {
    try {
      await api.post('/auth/logout');
    } finally {
      clear();
    }
  }

  configureApiAuth({
    getAccessToken: () => accessToken.value,
    getLocale: () => useLocaleStore().locale,
    refresh,
    onAuthFailure: clear,
  });

  return {
    accessToken,
    user,
    ready,
    isAuthenticated,
    isVerified,
    isAdmin,
    bootstrap,
    refresh,
    fetchUser,
    applySession,
    setUser,
    login,
    register,
    logout,
    clear,
  };
});
