import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));
vi.mock('@/lib/theme', () => ({ applyThemePreference: vi.fn() }));

import { applyThemePreference } from '@/lib/theme';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';

const user = {
  id: 1,
  name: 'Ann',
  email_verified: true,
  is_admin: false,
  theme_preference: 'dark',
  locale: 'ru',
};

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('bootstrap restores the session from the refresh cookie', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { access_token: 'at', user } });
    mockApi.get.mockResolvedValueOnce({ data: { data: user } });

    const auth = useAuthStore();
    await auth.bootstrap();

    expect(mockApi.post).toHaveBeenCalledWith('/auth/refresh');
    expect(auth.accessToken).toBe('at');
    expect(auth.user).toEqual(user);
    expect(auth.isAuthenticated).toBe(true);
    expect(auth.ready).toBe(true);
    expect(applyThemePreference).toHaveBeenCalledWith('dark');
    expect(useLocaleStore().locale).toBe('ru');
  });

  it('bootstrap ends as guest when there is no valid refresh cookie', async () => {
    mockApi.post.mockRejectedValueOnce(new Error('401'));

    const auth = useAuthStore();
    await auth.bootstrap();

    expect(auth.user).toBeNull();
    expect(auth.isAuthenticated).toBe(false);
    expect(auth.ready).toBe(true);
    expect(mockApi.get).not.toHaveBeenCalled();
  });

  it('bootstrap runs only once', async () => {
    mockApi.post.mockRejectedValue(new Error('401'));

    const auth = useAuthStore();
    await Promise.all([auth.bootstrap(), auth.bootstrap()]);
    await auth.bootstrap();

    expect(mockApi.post).toHaveBeenCalledTimes(1);
  });

  it('login sends the web client kind and stores the session', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { access_token: 'at', user } });

    const auth = useAuthStore();
    await auth.login({ email: 'a@b.c', password: 'x' });

    expect(mockApi.post).toHaveBeenCalledWith('/auth/login', {
      email: 'a@b.c',
      password: 'x',
      client: 'web',
    });
    expect(auth.accessToken).toBe('at');
    expect(auth.isVerified).toBe(true);
  });

  it('logout clears the session even if the request fails', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { access_token: 'at', user } });
    const auth = useAuthStore();
    await auth.login({ email: 'a@b.c', password: 'x' });

    mockApi.post.mockRejectedValueOnce(new Error('network'));
    await expect(auth.logout()).rejects.toThrow('network');

    expect(auth.accessToken).toBeNull();
    expect(auth.user).toBeNull();
  });
});
