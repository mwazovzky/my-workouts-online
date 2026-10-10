import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockApi, mockRouter, mockRoute } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn() },
  mockRouter: { push: vi.fn() },
  mockRoute: { query: {} },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));
vi.mock('vue-router', () => ({ useRouter: () => mockRouter, useRoute: () => mockRoute }));
vi.mock('@/composables/useTranslation', () => ({ useTranslation: () => ({ t: v => v }) }));

import Login from '@/pages/auth/Login.vue';
import { RouterLinkStub, withPinia } from '@/test/utils';
import { useAuthStore } from '@/stores/auth';

const user = {
  id: 1,
  name: 'Ann',
  email: 'ann@example.com',
  email_verified_at: '2026-10-01T09:30:00Z',
  is_admin: false,
};

function build() {
  return mount(Login, {
    global: {
      plugins: [withPinia()],
      stubs: { RouterLink: RouterLinkStub, GuestLayout: { template: '<div><slot /></div>' } },
    },
  });
}

describe('Login page', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockRoute.query = {};
  });

  it('logs in as a web client and redirects to the dashboard', async () => {
    mockApi.post.mockResolvedValueOnce({ data: { access_token: 'at', user } });
    const wrapper = build();

    await wrapper.find('#email').setValue('ann@example.com');
    await wrapper.find('#password').setValue('secret');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.post).toHaveBeenCalledWith('/auth/login', {
      email: 'ann@example.com',
      password: 'secret',
      client: 'web',
    });
    expect(useAuthStore().user).toEqual(user);
    expect(useAuthStore().accessToken).toBe('at');
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'dashboard' });
  });

  it('honours the redirect query after login', async () => {
    mockRoute.query = { redirect: '/workouts/3' };
    mockApi.post.mockResolvedValueOnce({ data: { access_token: 'at', user } });
    const wrapper = build();

    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockRouter.push).toHaveBeenCalledWith('/workouts/3');
  });

  it('shows wrong credentials under the email field and clears the password', async () => {
    mockApi.post.mockRejectedValueOnce({
      response: {
        status: 401,
        headers: {},
        data: { code: 'invalid_credentials', detail: 'These credentials do not match.' },
      },
    });
    const wrapper = build();

    await wrapper.find('#password').setValue('wrong');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('These credentials do not match.');
    expect(wrapper.find('#password').element.value).toBe('');
    expect(mockRouter.push).not.toHaveBeenCalled();
  });

  it('shows the lockout wait from Retry-After', async () => {
    mockApi.post.mockRejectedValueOnce({
      response: {
        status: 429,
        headers: { 'retry-after': '42' },
        data: { code: 'too_many_attempts', detail: 'Too Many Attempts.' },
      },
    });
    const wrapper = build();

    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('Too many login attempts. Try again in :seconds seconds.');
    expect(mockRouter.push).not.toHaveBeenCalled();
  });

  it('shows a malformed-input error from the 422 pointer', async () => {
    mockApi.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          code: 'validation_failed',
          errors: [{ pointer: '/email', code: 'invalid_format', detail: 'Enter a valid email.' }],
        },
      },
    });
    const wrapper = build();

    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('Enter a valid email.');
    expect(mockRouter.push).not.toHaveBeenCalled();
  });

  it('shows the reset confirmation when arriving from a password reset', () => {
    mockRoute.query = { reset: '1' };

    expect(build().text()).toContain('Your password has been reset.');
  });
});
