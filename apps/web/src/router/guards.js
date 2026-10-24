import { publicPagePath } from '@/lib/crawl';
import { useAuthStore } from '@/stores/auth';
import { storedLocale, useLocaleStore } from '@/stores/locale';

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

/**
 * Public pages exist once per language (`/privacy`, `/ru/privacy`). A
 * signed-in user stays in their profile language, and so does a guest who
 * chose one; they are sent to that version. Otherwise the URL decides, without
 * recording a choice.
 */
export function resolvePublicLocale(to, auth, localeStore, chosen = storedLocale()) {
  const { publicPage, locale } = to.meta;

  if (!publicPage) {
    return null;
  }

  const wanted = auth.isAuthenticated ? localeStore.locale : chosen;

  if (wanted && wanted !== locale) {
    return { path: publicPagePath(publicPage, wanted), query: to.query, hash: to.hash };
  }

  localeStore.setLocale(locale, { persist: false });

  return null;
}

export function installGuards(router) {
  router.beforeEach(async to => {
    const auth = useAuthStore();
    await auth.bootstrap();

    return resolveRedirect(to, auth) ?? resolvePublicLocale(to, auth, useLocaleStore()) ?? true;
  });
}
