import '@/assets/app.css';

import { createApp, createSSRApp } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import App from '@/App.vue';
import { resetHead } from '@/composables/usePageMeta';
import { createAppRouter } from '@/router';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';

const container = document.getElementById('app');
const pinia = createPinia();
const router = createAppRouter();

setActivePinia(pinia);
useAuthStore();

/**
 * A prerendered public page (`data-prerendered`) is shown as HTML while the
 * session is restored. Its markup is reused only when the app would render the
 * same thing: a guest still on that page. A signed-in visitor, or one sent to
 * another page or language, gets a fresh render and the default head instead.
 */
async function start() {
  const prerendered = container.dataset.prerendered;
  let hydrate = false;

  if (prerendered) {
    await router.push(router.options.history.location);
    await router.isReady();
    hydrate = router.currentRoute.value.path === prerendered && !useAuthStore().isAuthenticated;

    if (!hydrate) {
      container.replaceChildren();
      resetHead(document);
    }
  }

  const app = hydrate ? createSSRApp(App) : createApp(App);

  app.use(pinia);
  app.use(router);
  document.documentElement.lang = useLocaleStore().locale;
  app.mount(container);
}

start();
