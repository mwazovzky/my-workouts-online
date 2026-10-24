import { describe, expect, it } from 'vitest';
import { resolveRedirect } from '@/router/guards';

const guest = { isAuthenticated: false, isVerified: false, isAdmin: false };
const unverified = { isAuthenticated: true, isVerified: false, isAdmin: false };
const member = { isAuthenticated: true, isVerified: true, isAdmin: false };
const admin = { isAuthenticated: true, isVerified: true, isAdmin: true };

const route = (meta, fullPath = '/x', name = 'x') => ({ meta, fullPath, name });

describe('route guards', () => {
  it('sends signed-in users away from guest routes', () => {
    expect(resolveRedirect(route({ guest: true }), member)).toEqual({ name: 'dashboard' });
    expect(resolveRedirect(route({ guest: true }), guest)).toBeNull();
  });

  it('sends guests to login with a redirect back', () => {
    expect(resolveRedirect(route({ auth: true }, '/workouts/3'), guest)).toEqual({
      name: 'login',
      query: { redirect: '/workouts/3' },
    });
    expect(resolveRedirect(route({ verified: true }, '/'), guest)).toEqual({
      name: 'login',
      query: {},
    });
  });

  it('sends unverified users to the verification notice', () => {
    expect(resolveRedirect(route({ verified: true }), unverified)).toEqual({
      name: 'verification.notice',
    });
    expect(resolveRedirect(route({ auth: true }), unverified)).toBeNull();
    expect(
      resolveRedirect(route({ auth: true }, '/verify-email', 'verification.notice'), unverified)
    ).toBeNull();
  });

  it('keeps non-admins out of admin routes', () => {
    expect(resolveRedirect(route({ admin: true }), member)).toEqual({ name: 'dashboard' });
    expect(resolveRedirect(route({ admin: true }), admin)).toBeNull();
    expect(resolveRedirect(route({ admin: true }), unverified)).toEqual({
      name: 'verification.notice',
    });
  });

  it('allows public routes for everyone', () => {
    expect(resolveRedirect(route({}), guest)).toBeNull();
    expect(resolveRedirect(route({}), admin)).toBeNull();
  });
});

import { vi } from 'vitest';
import { installGuards } from '@/router/guards';
import { withPinia } from '@/test/utils';
import { useAuthStore } from '@/stores/auth';

describe('installGuards', () => {
  it('bootstraps the session before every navigation and applies the redirect', async () => {
    withPinia({ id: 1, email_verified_at: '2026-10-01T09:30:00Z', is_admin: false });
    const auth = useAuthStore();
    const bootstrap = vi.spyOn(auth, 'bootstrap').mockResolvedValue(undefined);
    const router = { beforeEach: vi.fn() };

    installGuards(router);
    const guard = router.beforeEach.mock.calls[0][0];

    expect(await guard({ meta: { guest: true }, fullPath: '/login', name: 'login' })).toEqual({
      name: 'dashboard',
    });
    expect(await guard({ meta: {}, fullPath: '/about', name: 'about' })).toBe(true);
    expect(bootstrap).toHaveBeenCalledTimes(2);
  });
});

import { resolvePublicLocale } from '@/router/guards';

describe('resolvePublicLocale', () => {
  const page = (path, locale) => ({
    path,
    meta: { publicPage: 'any', locale },
    query: { a: '1' },
    hash: '#top',
  });
  const store = locale => ({ locale, setLocale: vi.fn() });

  it('lets the URL set the language for a guest without a stored choice', () => {
    const localeStore = store('en');

    expect(resolvePublicLocale(page('/ru/privacy', 'ru'), guest, localeStore, null)).toBeNull();
    expect(localeStore.setLocale).toHaveBeenCalledWith('ru', { persist: false });
  });

  it('sends a guest to the version in the language they chose', () => {
    const localeStore = store('ru');

    expect(resolvePublicLocale(page('/privacy', 'en'), guest, localeStore, 'ru')).toEqual({
      path: '/ru/privacy',
      query: { a: '1' },
      hash: '#top',
    });
    expect(localeStore.setLocale).not.toHaveBeenCalled();
  });

  it('keeps a signed-in user in their profile language', () => {
    expect(resolvePublicLocale(page('/ru/support', 'ru'), member, store('en'), null)).toEqual({
      path: '/support',
      query: { a: '1' },
      hash: '#top',
    });
    expect(resolvePublicLocale(page('/support', 'en'), member, store('en'), 'ru')).toBeNull();
  });

  it('finds the other version of a program catalog page', () => {
    expect(
      resolvePublicLocale(
        page('/catalog/programs/gym-strength-5x5', 'en'),
        guest,
        store('en'),
        'ru'
      )
    ).toEqual({ path: '/ru/catalog/programs/gym-strength-5x5', query: { a: '1' }, hash: '#top' });
    expect(resolvePublicLocale(page('/ru', 'ru'), member, store('en'), null).path).toBe('/');
  });

  it('ignores routes that are not public pages', () => {
    const localeStore = store('en');

    expect(resolvePublicLocale({ meta: {} }, guest, localeStore, 'ru')).toBeNull();
    expect(localeStore.setLocale).not.toHaveBeenCalled();
  });
});
