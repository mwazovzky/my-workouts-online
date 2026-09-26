import { createRouter, createWebHistory } from 'vue-router';
import { installGuards } from '@/router/guards';
import { routes } from '@/router/routes';

export function createAppRouter() {
  const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
  });

  installGuards(router);

  return router;
}
