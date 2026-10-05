import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import { RouterLinkStub, withPinia } from '@/test/utils';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => `translated:${value}` }),
}));

import Support from '@/pages/Support.vue';

describe('Support', () => {
  function buildWrapper(user = null) {
    return mount(Support, {
      global: {
        plugins: [withPinia(user)],
        stubs: {
          AuthenticatedLayout: {
            template: '<div data-testid="authenticated-layout"><slot /></div>',
          },
          RouterLink: RouterLinkStub,
          ApplicationLogo: { template: '<div />' },
          GuestLocaleSwitcher: { template: '<div />' },
        },
      },
    });
  }

  it('shows the support address and what to include', () => {
    const wrapper = buildWrapper();
    expect(wrapper.find('a[href="mailto:support@my-workouts.online"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('translated:The email address of your account');
  });

  it('points to self-service deletion and the privacy policy', () => {
    const wrapper = buildWrapper();
    expect(wrapper.text()).toContain('translated:Deleting your account');
    expect(wrapper.text()).toContain('translated:Privacy Policy');
  });

  it('uses the guest layout when signed out', () => {
    const wrapper = buildWrapper();
    expect(wrapper.find('[data-testid="authenticated-layout"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('My Workouts Online');
  });

  it('keeps the app navigation when signed in', () => {
    const wrapper = buildWrapper({ name: 'John', email: 'john@example.com' });
    expect(wrapper.find('[data-testid="authenticated-layout"]').exists()).toBe(true);
    expect(wrapper.find('a[href="mailto:support@my-workouts.online"]').exists()).toBe(true);
  });
});
