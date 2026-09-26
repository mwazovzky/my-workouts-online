import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

let mockUser = { name: 'Jane', is_admin: false };

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: { user: mockUser } } }),
}));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({
    t: value => value,
  }),
}));

import Navigation from '@/Components/Navigation.vue';

describe('Navigation', () => {
  function buildWrapper() {
    return mount(Navigation, {
      global: {
        config: {
          globalProperties: {
            route: name => (name ? `/${name}` : { current: () => false }),
          },
        },
        stubs: {
          NavLink: {
            props: ['href', 'active'],
            template: '<a :href="href"><slot /></a>',
          },
        },
      },
    });
  }

  it('renders all navigation links', () => {
    mockUser = { name: 'Jane', is_admin: false };
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Programs');
    expect(wrapper.text()).toContain('Workouts');
    expect(wrapper.text()).toContain('About');
  });

  it('renders the About link with the correct href', () => {
    mockUser = { name: 'Jane', is_admin: false };
    const wrapper = buildWrapper();
    const links = wrapper.findAll('a');
    const aboutLink = links.find(l => l.text().trim() === 'About');

    expect(aboutLink).toBeDefined();
    expect(aboutLink.attributes('href')).toBe('/about');
  });

  it('hides the Admin link for non-admin users', () => {
    mockUser = { name: 'Jane', is_admin: false };
    const wrapper = buildWrapper();

    expect(wrapper.text()).not.toContain('Admin');
  });

  it('hides the Admin link when there is no authenticated user', () => {
    mockUser = null;
    const wrapper = buildWrapper();

    expect(wrapper.text()).not.toContain('Admin');
  });

  it('shows the Admin link for admin users', () => {
    mockUser = { name: 'Jane', is_admin: true };
    const wrapper = buildWrapper();
    const links = wrapper.findAll('a');
    const adminLink = links.find(l => l.text().trim() === 'Admin');

    expect(adminLink).toBeDefined();
    expect(adminLink.attributes('href')).toBe('/admin.index');
  });
});
