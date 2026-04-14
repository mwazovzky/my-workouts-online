import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

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
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Programs');
    expect(wrapper.text()).toContain('Workouts');
    expect(wrapper.text()).toContain('About');
  });

  it('renders the About link with the correct href', () => {
    const wrapper = buildWrapper();
    const links = wrapper.findAll('a');
    const aboutLink = links.find(l => l.text().trim() === 'About');

    expect(aboutLink).toBeDefined();
    expect(aboutLink.attributes('href')).toBe('/about');
  });
});
