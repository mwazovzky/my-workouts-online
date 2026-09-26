import { useAuthStore } from '@/stores/auth';

/**
 * Route meta: `guest` (only when signed out), `auth` (signed in),
 * `verified` (signed in + email verified), `admin` (signed in + admin).
 */
export function resolveRedirect(to, auth) {
  const { guest, auth: requiresAuth, verified, admin } = to.meta;

  if (guest && auth.isAuthenticated) {
    return { name: 'dashboard' };
  }

  const needsUser = requiresAuth || verified || admin;

  if (needsUser && !auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
  }

  if ((verified || admin) && !auth.isVerified) {
    return to.name === 'verification.notice' ? null : { name: 'verification.notice' };
  }

  if (admin && !auth.isAdmin) {
    return { name: 'dashboard' };
  }

  return null;
}

export function installGuards(router) {
  router.beforeEach(async to => {
    const auth = useAuthStore();
    await auth.bootstrap();

    return resolveRedirect(to, auth) ?? true;
  });
}
