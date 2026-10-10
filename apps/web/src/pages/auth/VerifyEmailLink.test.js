import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockApi, mockRouter, mockRoute } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn() },
  mockRouter: { replace: vi.fn() },
  mockRoute: {
    params: { id: '5', hash: 'abc' },
    query: { expires: '1700000000', signature: 'sig' },
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));
vi.mock('vue-router', () => ({ useRouter: () => mockRouter, useRoute: () => mockRoute }));
vi.mock('@/composables/useTranslation', () => ({ useTranslation: () => ({ t: v => v }) }));

import VerifyEmailLink from '@/pages/auth/VerifyEmailLink.vue';
import { RouterLinkStub, withPinia } from '@/test/utils';
import { useAuthStore } from '@/stores/auth';

function build() {
  return mount(VerifyEmailLink, {
    global: {
      plugins: [withPinia({ id: 5, email_verified_at: null })],
      stubs: { RouterLink: RouterLinkStub, GuestLayout: { template: '<div><slot /></div>' } },
    },
  });
}

describe('VerifyEmailLink page', () => {
  beforeEach(() => vi.clearAllMocks());

  it('posts the link parameters, refreshes the user and continues to the dashboard', async () => {
    mockApi.post.mockResolvedValueOnce({});
    mockApi.get.mockResolvedValueOnce({
      data: { data: { id: 5, email_verified_at: '2026-10-01T09:30:00Z' } },
    });

    build();
    await flushPromises();

    expect(mockApi.post).toHaveBeenCalledWith('/auth/email/verify', {
      id: 5,
      hash: 'abc',
      expires: 1700000000,
      signature: 'sig',
    });
    expect(useAuthStore().isVerified).toBe(true);
    expect(mockRouter.replace).toHaveBeenCalledWith({ name: 'dashboard', query: { verified: 1 } });
  });

  it('shows the API message when the link is rejected', async () => {
    mockApi.post.mockRejectedValueOnce({
      response: { data: { detail: 'Link expired.', code: 'invalid_signature' } },
    });

    const wrapper = build();
    await flushPromises();

    expect(wrapper.text()).toContain('Link expired.');
    expect(wrapper.text()).toContain('Resend Verification Email');
    expect(mockRouter.replace).not.toHaveBeenCalled();
  });
});
