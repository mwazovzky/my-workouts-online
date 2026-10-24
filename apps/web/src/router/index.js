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
    // Hash links such as a program page's schedule scroll to their section.
    scrollBehavior: (to, from, saved) => saved ?? (to.hash ? { el: to.hash } : { top: 0 }),
  });

  installGuards(router);

  return router;
}
