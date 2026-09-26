import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({ api: { get: vi.fn(), post: vi.fn() }, configureApiAuth: vi.fn() }));

import App from '@/App.vue';
import { withPinia } from '@/test/utils';
import { useAuthStore } from '@/stores/auth';

describe('App', () => {
  it('shows a placeholder until the session is bootstrapped, then the router view', async () => {
    const wrapper = mount(App, {
      global: { plugins: [withPinia()], stubs: { RouterView: { template: '<main>page</main>' } } },
    });

    expect(wrapper.text()).toContain('…');
    expect(wrapper.find('main').exists()).toBe(false);

    useAuthStore().ready = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('main').text()).toBe('page');
  });
});
