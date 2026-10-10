import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockApi, mockRouter, mockRoute } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn() },
  mockRouter: { push: vi.fn() },
  mockRoute: { params: { token: 'tok' }, query: { email: 'ann@example.com' } },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));
vi.mock('vue-router', () => ({ useRouter: () => mockRouter, useRoute: () => mockRoute }));
vi.mock('@/composables/useTranslation', () => ({ useTranslation: () => ({ t: v => v }) }));

import Register from '@/pages/auth/Register.vue';
import ResetPassword from '@/pages/auth/ResetPassword.vue';
import { RouterLinkStub, withPinia } from '@/test/utils';

function build(component) {
  return mount(component, {
    global: {
      plugins: [withPinia()],
      stubs: { RouterLink: RouterLinkStub, GuestLayout: { template: '<div><slot /></div>' } },
    },
  });
}

describe('password confirmation stays on the client', () => {
  beforeEach(() => vi.clearAllMocks());

  it('register sends no password_confirmation', async () => {
    mockApi.post.mockResolvedValueOnce({
      data: { access_token: 'at', user: { id: 1, email_verified_at: null } },
    });
    const wrapper = build(Register);
    await wrapper.find('#name').setValue('Ann');
    await wrapper.find('#email').setValue('ann@example.com');
    await wrapper.find('#password').setValue('secret-123');
    await wrapper.find('#password_confirmation').setValue('secret-123');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.post).toHaveBeenCalledWith('/auth/register', {
      name: 'Ann',
      email: 'ann@example.com',
      password: 'secret-123',
      client: 'web',
    });
  });

  it('register mismatch is reported locally', async () => {
    const wrapper = build(Register);
    await wrapper.find('#password').setValue('secret-123');
    await wrapper.find('#password_confirmation').setValue('other');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.post).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('The password confirmation does not match.');
  });

  it('reset sends token, email and password only', async () => {
    mockApi.post.mockResolvedValueOnce({});
    const wrapper = build(ResetPassword);
    await wrapper.find('#password').setValue('secret-123');
    await wrapper.find('#password_confirmation').setValue('secret-123');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.post).toHaveBeenCalledWith('/auth/password/reset', {
      token: 'tok',
      email: 'ann@example.com',
      password: 'secret-123',
    });
  });

  it('reset mismatch is reported locally', async () => {
    const wrapper = build(ResetPassword);
    await wrapper.find('#password').setValue('secret-123');
    await wrapper.find('#password_confirmation').setValue('other');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.post).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('The password confirmation does not match.');
  });
});
