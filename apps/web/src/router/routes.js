/**
 * Placeholder route table for the shell. Story 7 fills it with every page.
 */
export const routes = [
  {
    path: '/',
    name: 'welcome',
    component: () => import('@/pages/Welcome.vue'),
    meta: { guest: true },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/Login.vue'),
    meta: { guest: true },
  },
  {
    path: '/verify-email',
    name: 'verification.notice',
    component: () => import('@/pages/VerifyEmail.vue'),
    meta: { auth: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/pages/Dashboard.vue'),
    meta: { verified: true },
  },
];
