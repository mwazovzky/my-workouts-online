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

import BottomNav from '@/Components/BottomNav.vue';

describe('BottomNav', () => {
  function buildWrapper(currentRoute = null) {
    return mount(BottomNav, {
      global: {
        config: {
          globalProperties: {
            route: name => (name ? `/${name}` : { current: pattern => pattern === currentRoute }),
          },
        },
      },
    });
  }

  it('renders all four tab labels', () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Programs');
    expect(wrapper.text()).toContain('Workouts');
    expect(wrapper.text()).toContain('Profile');
  });

  it('renders each tab with the correct href', () => {
    const wrapper = buildWrapper();
    const links = wrapper.findAll('a');

    const hrefs = links.map(l => l.attributes('href'));
    expect(hrefs).toContain('/dashboard');
    expect(hrefs).toContain('/programs.index');
    expect(hrefs).toContain('/workouts.index');
    expect(hrefs).toContain('/profile.edit');
  });

  it('applies text-primary to the active tab', () => {
    const wrapper = buildWrapper('dashboard');
    const links = wrapper.findAll('a');
    const dashboardLink = links.find(l => l.attributes('href') === '/dashboard');

    expect(dashboardLink.classes()).toContain('text-primary');
  });

  it('applies text-muted-foreground to inactive tabs', () => {
    const wrapper = buildWrapper('dashboard');
    const links = wrapper.findAll('a');
    const inactiveLinks = links.filter(l => l.attributes('href') !== '/dashboard');

    inactiveLinks.forEach(link => {
      expect(link.classes()).toContain('text-muted-foreground');
    });
  });

  it('renders an svg icon for each tab', () => {
    const wrapper = buildWrapper();
    const svgs = wrapper.findAll('svg');

    expect(svgs).toHaveLength(4);
  });
});
