import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import { RouterLinkStub } from '@/test/utils';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => `translated:${value}` }),
}));

import Support from '@/pages/Support.vue';

describe('Support', () => {
  function buildWrapper() {
    return mount(Support, {
      global: {
        stubs: {
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
});
