import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
  Head: {
    template: '<div><slot /></div>',
  },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({
    t: value => `translated:${value}`,
  }),
}));

import Welcome from '@/Pages/Welcome.vue';

describe('Welcome', () => {
  function buildWrapper(props = {}) {
    return mount(Welcome, {
      props: {
        canLogin: true,
        canRegister: true,
        ...props,
      },
      global: {
        config: {
          globalProperties: {
            route: name => `/${name}`,
          },
        },
        stubs: {
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