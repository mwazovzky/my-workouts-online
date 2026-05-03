import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}));

import GuestLayout from '@/Layouts/GuestLayout.vue';

describe('GuestLayout', () => {
  function buildWrapper(slots = {}) {
    return mount(GuestLayout, {
      slots,
      global: {
        stubs: {
          ApplicationLogo: { template: '<div />' },
          GuestLocaleSwitcher: { template: '<div data-testid="locale-switcher" />' },
        },
      },
    });
  }

  it('renders a link back to the home page', () => {
    const wrapper = buildWrapper();
    const homeLink = wrapper.find('a[href="/"]');
    expect(homeLink.exists()).toBe(true);
  });

  it('renders the app name', () => {
    const wrapper = buildWrapper();
    expect(wrapper.text()).toContain('My Workouts Online');
  });

  it('renders the locale switcher', () => {
    const wrapper = buildWrapper();
    expect(wrapper.find('[data-testid="locale-switcher"]').exists()).toBe(true);
  });

  it('renders slot content', () => {
    const wrapper = buildWrapper({ default: '<p>Form content</p>' });
    expect(wrapper.text()).toContain('Form content');
  });
});
