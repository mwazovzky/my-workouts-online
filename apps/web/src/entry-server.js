import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createMemoryHistory } from 'vue-router';
import { createPinia } from 'pinia';
import catalog from 'virtual:public-catalog';
import App from '@/App.vue';
import { publicPaths } from '@/lib/crawl';
import { createAppRouter } from '@/router';
import { useLocaleStore } from '@/stores/locale';

export { applyHeadTag } from '@/composables/usePageMeta';

/**
 * Every page to prerender: the fixed public pages and the program catalog.
 */
export const PRERENDER_PATHS = publicPaths(catalog);

/**
 * Renders the app at `path` as a signed-out visitor, for `prerender.js`.
 * Returns the app's HTML, the page head set by `usePageMeta`, the language
 * and the path the router settled on.
 */
export async function render(path) {
  const app = createSSRApp(App);
  const pinia = createPinia();
  const router = createAppRouter(createMemoryHistory());

  app.use(pinia);
  app.use(router);

  await router.push(path);
  await router.isReady();

  const context = {};
  const html = await renderToString(app, context);

  return {
    html,
    head: context.head,
    locale: useLocaleStore(pinia).locale,
    path: router.currentRoute.value.path,
  };
}
