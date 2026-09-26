import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockApi } = vi.hoisted(() => ({ mockApi: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));

import GuestLocaleSwitcher from '@/components/GuestLocaleSwitcher.vue';
import { withPinia } from '@/test/utils';
import { useLocaleStore } from '@/stores/locale';

function build(props = {}) {
  return mount(GuestLocaleSwitcher, {
    props,
    global: {
      plugins: [withPinia()],
      stubs: {
        Dropdown: { template: '<div><slot name="trigger" /><slot name="content" /></div>' },
      },
    },
  });
}

describe('GuestLocaleSwitcher', () => {
  beforeEach(() => {
    window.localStorage.clear();
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({
      data: {
        data: { available_locales: { en: 'English', ru: 'Русский' }, theme_preferences: [] },
      },
    });
  });

  it('loads the locale list and shows the current locale label', async () => {
    const wrapper = build();
    await flushPromises();

    expect(mockApi.get).toHaveBeenCalledWith('/meta');
    expect(wrapper.text()).toContain('English');
    expect(wrapper.text()).toContain('Русский');
  });

  it('switches the locale store when an option is chosen', async () => {
    const wrapper = build();
    await flushPromises();

    await wrapper
      .findAll('button')
      .find(b => b.text().includes('Русский'))
      .trigger('click');

    expect(useLocaleStore().locale).toBe('ru');
    expect(wrapper.text()).toContain('✓');
  });

  it('falls back to the upper-cased code before the list loads', async () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    window.localStorage.setItem('locale', 'ru');
    const wrapper = build({ inverted: true });

    expect(wrapper.text()).toContain('RU');
    expect(wrapper.find('button').classes().join(' ')).toContain('text-background');
  });
});
