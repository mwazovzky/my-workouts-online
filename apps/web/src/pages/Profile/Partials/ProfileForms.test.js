import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockApi, mockRouter } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  mockRouter: { push: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));
vi.mock('vue-router', () => ({ useRouter: () => mockRouter }));
vi.mock('vue-sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/composables/useTranslation', () => ({ useTranslation: () => ({ t: v => v }) }));
vi.mock('@/lib/theme', () => ({
  applyThemePreference: vi.fn(),
  currentThemePreference: () => 'system',
}));

import UpdateProfileInformationForm from '@/pages/Profile/Partials/UpdateProfileInformationForm.vue';
import UpdateLanguageForm from '@/pages/Profile/Partials/UpdateLanguageForm.vue';
import UpdateThemeForm from '@/pages/Profile/Partials/UpdateThemeForm.vue';
import UpdatePasswordForm from '@/pages/Profile/Partials/UpdatePasswordForm.vue';
import DeleteUserForm from '@/pages/Profile/Partials/DeleteUserForm.vue';
import { withPinia } from '@/test/utils';

const user = {
  id: 1,
  name: 'Ann',
  email: 'ann@example.com',
  locale: 'en',
  theme_preference: 'light',
  email_verified_at: '2026-10-01T09:30:00Z',
  is_admin: false,
};

function build(component) {
  return mount(component, {
    global: {
      plugins: [withPinia(user)],
      stubs: { Modal: { template: '<div><slot /></div>' }, Transition: false },
    },
  });
}

describe('profile forms', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({
      data: {
        data: {
          available_locales: { en: 'English', ru: 'Русский' },
          theme_preferences: ['light', 'dark', 'system'],
        },
      },
    });
    mockApi.patch.mockResolvedValue({ data: { data: user } });
    mockApi.put.mockResolvedValue({});
    mockApi.delete.mockResolvedValue({});
  });

  it('profile information patches /me with name and email only', async () => {
    const wrapper = build(UpdateProfileInformationForm);
    await wrapper.find('#name').setValue('Bea');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.patch).toHaveBeenCalledWith('/me', { name: 'Bea', email: 'ann@example.com' });
  });

  it('language patches /me with the locale only', async () => {
    const wrapper = build(UpdateLanguageForm);
    await flushPromises();
    await wrapper.find('#locale').setValue('ru');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.patch).toHaveBeenCalledWith('/me', { locale: 'ru' });
  });

  it('theme patches /me with the theme preference only', async () => {
    const wrapper = build(UpdateThemeForm);
    await wrapper.find('#theme_preference').setValue('dark');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.patch).toHaveBeenCalledWith('/me', { theme_preference: 'dark' });
  });

  it('password puts /me/password without a confirmation field', async () => {
    const wrapper = build(UpdatePasswordForm);
    await wrapper.find('#current_password').setValue('old-secret');
    await wrapper.find('#password').setValue('new-secret');
    await wrapper.find('#password_confirmation').setValue('new-secret');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.put).toHaveBeenCalledWith('/me/password', {
      current_password: 'old-secret',
      password: 'new-secret',
    });
  });

  it('password mismatch is reported locally without calling the API', async () => {
    const wrapper = build(UpdatePasswordForm);
    await wrapper.find('#current_password').setValue('old-secret');
    await wrapper.find('#password').setValue('new-secret');
    await wrapper.find('#password_confirmation').setValue('other');
    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(mockApi.put).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('The password confirmation does not match.');
  });

  it('delete sends DELETE /me with the password', async () => {
    const wrapper = build(DeleteUserForm);
    const buttons = () => wrapper.findAll('button');
    await buttons()[0].trigger('click');
    await wrapper.find('#password').setValue('secret');
    await buttons()
      .filter(b => b.text() === 'Delete Account')
      .at(-1)
      .trigger('click');
    await flushPromises();

    expect(mockApi.delete).toHaveBeenCalledWith('/me', { data: { password: 'secret' } });
  });
});
