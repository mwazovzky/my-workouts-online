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
    withPinia({ id: 1, email_verified: true, is_admin: false });
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
