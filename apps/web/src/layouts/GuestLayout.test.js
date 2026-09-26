import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import { RouterLinkStub } from '@/test/utils';

import GuestLayout from '@/layouts/GuestLayout.vue';

describe('GuestLayout', () => {
  function buildWrapper(slots = {}) {
    return mount(GuestLayout, {
      slots,
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
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
