import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createMemoryHistory } from 'vue-router';
import { createPinia, setActivePinia } from 'pinia';

const { mockApi } = vi.hoisted(() => ({ mockApi: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));

import App from '@/App.vue';
import { PUBLIC_PATHS } from '@/lib/crawl';
import { createAppRouter } from '@/router';

/**
 * The app at `path` for a signed-out visitor, as main.js builds it before
 * deciding to hydrate.
 */
async function buildApp(path) {
  const app = createSSRApp(App);
  const pinia = createPinia();
  const router = createAppRouter(createMemoryHistory());

  setActivePinia(pinia);
  app.use(pinia);
  app.use(router);
  await router.push(path);
  await router.isReady();

  return app;
}

describe('prerendered public pages', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it.each(PUBLIC_PATHS)('%s hydrates over its server markup without mismatches', async path => {
    window.localStorage.clear();
    mockApi.post.mockRejectedValue(new Error('signed out'));
    mockApi.get.mockReturnValue(new Promise(() => {}));

    const html = await renderToString(await buildApp(path));
    const container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container);
    const heading = container.querySelector('h1');

    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const app = await buildApp(path);
    app.mount(container);
    await flushPromises();

    const messages = [...warn.mock.calls, ...error.mock.calls]
      .flat()
      .map(argument => (typeof argument === 'symbol' ? argument.toString() : String(argument)))
      .join('\n');
    expect(messages).not.toMatch(/hydrat|mismatch/i);
    expect(heading).not.toBeNull();
    expect(container.querySelector('h1')).toBe(heading);

    app.unmount();
  });
});
