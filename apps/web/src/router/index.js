import { createRouter, createWebHistory } from 'vue-router';
import { installGuards } from '@/router/guards';
import { routes } from '@/router/routes';

/**
 * `history` defaults to the browser's; prerendering passes a memory history.
 */
export function createAppRouter(history = createWebHistory()) {
  const router = createRouter({
    history,
    routes,
    scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
  });

  installGuards(router);

  return router;
}
