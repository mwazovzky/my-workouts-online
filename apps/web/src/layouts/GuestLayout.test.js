import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

import { RouterLinkStub, withPinia } from '@/test/utils';
import { useLocaleStore } from '@/stores/locale';

import GuestLayout from '@/layouts/GuestLayout.vue';

describe('GuestLayout', () => {
  function buildWrapper(slots = {}) {
    return mount(GuestLayout, {
      slots,
      global: {
        plugins: [withPinia()],
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

  it('links the logo to the home page in the current language', () => {
    const pinia = withPinia();
    useLocaleStore().setLocale('ru', { persist: false });
    const wrapper = mount(GuestLayout, {
      global: {
        plugins: [pinia],
        stubs: {
          RouterLink: RouterLinkStub,
          ApplicationLogo: { template: '<div />' },
          GuestLocaleSwitcher: { template: '<div />' },
        },
      },
    });

    expect(wrapper.find('a[href="/ru"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/ru/privacy"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/ru/support"]').exists()).toBe(true);
  });

  it('renders the app name', () => {
    const wrapper = buildWrapper();
    expect(wrapper.text()).toContain('My Workouts Online');
  });

  it('renders the locale switcher', () => {
    const wrapper = buildWrapper();
    expect(wrapper.find('[data-testid="locale-switcher"]').exists()).toBe(true);
  });

  it('links to the privacy policy and support pages', () => {
    const wrapper = buildWrapper();
    expect(wrapper.text()).toContain('Privacy Policy');
    expect(wrapper.text()).toContain('Support');
  });

  it('renders slot content', () => {
    const wrapper = buildWrapper({ default: '<p>Form content</p>' });
    expect(wrapper.text()).toContain('Form content');
  });
});
