import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import { RouterLinkStub } from '@/test/utils';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({
    t: value => `translated:${value}`,
  }),
}));

import Welcome from '@/pages/Welcome.vue';

describe('Welcome', () => {
  function buildWrapper() {
    return mount(Welcome, {
      global: {
        config: {
          globalProperties: {
            route: name => `/${name}`,
          },
        },
        stubs: {
          RouterLink: RouterLinkStub,
          ApplicationLogo: {
            template: '<div />',
          },
          GuestLocaleSwitcher: {
            template: '<div />',
          },
        },
      },
    });
  }

  it('always offers login and registration', () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('translated:Log in');
    expect(wrapper.text()).toContain('translated:Create account');
    expect(wrapper.text()).toContain('translated:Privacy Policy');
    expect(wrapper.text()).toContain('translated:Support');
  });

  it('translates the upcoming workout preview labels', () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('translated:Monday');
    expect(wrapper.text()).toContain('translated:Upper Body');
    expect(wrapper.text()).toContain('translated:Strength program');
    expect(wrapper.text()).toContain('translated:Wednesday');
    expect(wrapper.text()).toContain('translated:Legs & Conditioning');
    expect(wrapper.text()).toContain('translated:Progress stays visible');
  });
});
