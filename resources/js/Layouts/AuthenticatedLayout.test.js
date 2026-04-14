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
          Dropdown: { template: '<div><slot name="trigger" /><slot name="content" /></div>' },
          DropdownLink: { props: ['href'], template: '<a :href="href"><slot /></a>' },
          ResponsiveNavLink: {
            props: ['href', 'active'],
            template: '<a :href="href"><slot /></a>',
          },
          ApplicationLogo: { template: '<div />' },
          Toaster: { template: '<div />' },
        },
        mocks: {
          $page: { props: { auth: { user: authUser } } },
        },
      },
    });
  }

  it('renders responsive nav links including About', () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Programs');
    expect(wrapper.text()).toContain('Workouts');
    expect(wrapper.text()).toContain('About');
  });

  it('renders the About responsive link with the correct href', () => {
    const wrapper = buildWrapper();
    const links = wrapper.findAll('a');
    const aboutLink = links.find(l => l.text().trim() === 'About');

    expect(aboutLink).toBeDefined();
    expect(aboutLink.attributes('href')).toBe('/about');
  });
});
