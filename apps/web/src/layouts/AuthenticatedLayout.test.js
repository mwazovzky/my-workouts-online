import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import { RouterLinkStub, withPinia } from '@/test/utils';

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({
    t: value => value,
  }),
}));

import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';

describe('AuthenticatedLayout', () => {
  const authUser = { name: 'John', email: 'john@example.com' };

  function buildWrapper() {
    return mount(AuthenticatedLayout, {
      global: {
        plugins: [withPinia(authUser)],
        stubs: {
          RouterLink: RouterLinkStub,
          Navigation: { template: '<nav />' },
          BottomNav: {
            template:
              '<nav><a href="/dashboard">Dashboard</a><a href="/programs.index">Programs</a><a href="/workouts.index">Workouts</a><a href="/profile.edit">Profile</a></nav>',
          },
          Dropdown: { template: '<div><slot name="trigger" /><slot name="content" /></div>' },
          DropdownLink: { props: ['to'], template: '<a :href="`/${to.name}`"><slot /></a>' },
          ApplicationLogo: { template: '<div />' },
          Toaster: { template: '<div />' },
        },
      },
    });
  }

  it('renders bottom nav tabs', () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Programs');
    expect(wrapper.text()).toContain('Workouts');
    expect(wrapper.text()).toContain('Profile');
  });

  it('renders the Profile tab with the correct href', () => {
    const wrapper = buildWrapper();
    const links = wrapper.findAll('a');
    const profileLink = links.find(l => l.text().trim() === 'Profile');

    expect(profileLink).toBeDefined();
    expect(profileLink.attributes('href')).toBe('/profile.edit');
  });

  it('renders the header slot when provided', () => {
    const wrapper = mount(AuthenticatedLayout, {
      slots: {
        header: '<h1>Page Title</h1>',
      },
      global: {
        plugins: [withPinia(authUser)],
        stubs: {
          RouterLink: RouterLinkStub,
          Navigation: { template: '<nav />' },
          BottomNav: { template: '<nav />' },
          Dropdown: { template: '<div><slot name="trigger" /><slot name="content" /></div>' },
          DropdownLink: { props: ['to'], template: '<a :href="`/${to.name}`"><slot /></a>' },
          ApplicationLogo: { template: '<div />' },
          Toaster: { template: '<div />' },
        },
      },
    });

    expect(wrapper.find('header').exists()).toBe(true);
    expect(wrapper.text()).toContain('Page Title');
  });

  it('links to the privacy policy and support pages', () => {
    const wrapper = buildWrapper();

    expect(wrapper.find('main a[href="/privacy"]').text()).toBe('Privacy Policy');
    expect(wrapper.find('main a[href="/support"]').text()).toBe('Support');
  });
});
