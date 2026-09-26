import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockRouter } = vi.hoisted(() => ({ mockRouter: { push: vi.fn() } }));
vi.mock('vue-router', () => ({ useRouter: () => mockRouter }));
vi.mock('@/lib/api', () => ({ api: { get: vi.fn(), post: vi.fn() }, configureApiAuth: vi.fn() }));

import { useLogout } from '@/composables/useLogout';
import { withPinia } from '@/test/utils';
import { useAuthStore } from '@/stores/auth';

describe('useLogout', () => {
  beforeEach(() => vi.clearAllMocks());

  it('logs out through the store and returns to the welcome page', async () => {
    withPinia({ id: 1 });
    const auth = useAuthStore();
    vi.spyOn(auth, 'logout').mockResolvedValue(undefined);

    await useLogout().logout();

    expect(auth.logout).toHaveBeenCalled();
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'welcome' });
  });

  it('still navigates home when the logout request fails', async () => {
    withPinia({ id: 1 });
    vi.spyOn(useAuthStore(), 'logout').mockRejectedValue(new Error('network'));

    await expect(useLogout().logout()).rejects.toThrow('network');
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'welcome' });
  });
});
