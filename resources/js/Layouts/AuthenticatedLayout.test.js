import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({
    t: value => value,
  }),
}));

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

describe('AuthenticatedLayout', () => {
  const authUser = { name: 'John', email: 'john@example.com' };

  function buildWrapper() {
    return mount(AuthenticatedLayout, {
      global: {
        config: {
          globalProperties: {
            route: name => (name ? `/${name}` : { current: () => false }),
            $page: { props: { auth: { user: authUser } } },
          },
        },
        stubs: {
          Navigation: { template: '<nav />' },
          BottomNav: {
            template:
              '<nav><a href="/dashboard">Dashboard</a><a href="/programs.index">Programs</a><a href="/workouts.index">Workouts</a><a href="/profile.edit">Profile</a></nav>',
          },
          Dropdown: { template: '<div><slot name="trigger" /><slot name="content" /></div>' },
          DropdownLink: { props: ['href'], template: '<a :href="href"><slot /></a>' },
          ApplicationLogo: { template: '<div />' },
          Toaster: { template: '<div />' },
        },
        mocks: {
          $page: { props: { auth: { user: authUser } } },
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
        config: {
          globalProperties: {
            route: name => (name ? `/${name}` : { current: () => false }),
            $page: { props: { auth: { user: authUser } } },
          },
        },
        stubs: {
          Navigation: { template: '<nav />' },
          BottomNav: { template: '<nav />' },
          Dropdown: { template: '<div><slot name="trigger" /><slot name="content" /></div>' },
          DropdownLink: { props: ['href'], template: '<a :href="href"><slot /></a>' },
          ApplicationLogo: { template: '<div />' },
          Toaster: { template: '<div />' },
        },
        mocks: {
          $page: { props: { auth: { user: authUser } } },
        },
      },
    });

    expect(wrapper.find('header').exists()).toBe(true);
    expect(wrapper.text()).toContain('Page Title');
  });
});
