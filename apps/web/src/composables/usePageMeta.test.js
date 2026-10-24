import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { siteUrl, usePageMeta } from '@/composables/usePageMeta';

const DEFAULT_DESCRIPTION = 'Default description.';
const DEFAULT_TITLE = 'Default title';

const content = selector => document.head.querySelector(selector)?.getAttribute('content');
const canonical = () => document.head.querySelector('link[rel="canonical"]')?.getAttribute('href');

function mountPage(meta) {
  return mount(
    defineComponent({
      setup() {
        usePageMeta(meta);
        return () => h('div');
      },
    })
  );
}

describe('usePageMeta', () => {
  beforeEach(() => {
    document.head.innerHTML = `
      <meta name="description" content="${DEFAULT_DESCRIPTION}">
      <meta property="og:title" content="${DEFAULT_TITLE}">
      <meta property="og:description" content="${DEFAULT_DESCRIPTION}">`;
  });

  afterEach(() => {
    document.head.innerHTML = '';
  });

  it('sets the title, description, canonical URL and Open Graph tags', () => {
    mountPage({ title: 'Support', description: 'Get help.', path: '/support' });

    expect(document.title).toBe('Support – My Workouts Online');
    expect(content('meta[name="description"]')).toBe('Get help.');
    expect(content('meta[property="og:title"]')).toBe('Support – My Workouts Online');
    expect(content('meta[property="og:description"]')).toBe('Get help.');
    expect(content('meta[property="og:url"]')).toBe(`${siteUrl()}/support`);
    expect(canonical()).toBe(`${siteUrl()}/support`);
    expect(document.head.querySelectorAll('link[rel="canonical"]')).toHaveLength(1);
  });

  it('follows reactive values, such as a locale change', async () => {
    const description = ref('Get help.');
    const wrapper = mountPage({ title: () => 'Support', description, path: '/support' });

    description.value = 'Получите помощь.';
    await wrapper.vm.$nextTick();

    expect(content('meta[name="description"]')).toBe('Получите помощь.');
    expect(content('meta[property="og:description"]')).toBe('Получите помощь.');
  });

  it('restores the defaults and removes the tags it added on unmount', () => {
    const wrapper = mountPage({ title: 'Support', description: 'Get help.', path: '/support' });

    wrapper.unmount();

    expect(content('meta[name="description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(content('meta[property="og:title"]')).toBe(DEFAULT_TITLE);
    expect(content('meta[property="og:description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(content('meta[property="og:url"]')).toBeUndefined();
    expect(canonical()).toBeUndefined();
  });

  it('leaves the tags of the next page alone when the previous page unmounts late', () => {
    const leaving = mountPage({ title: 'Support', description: 'Get help.', path: '/support' });
    const entering = mountPage({
      title: 'Privacy Policy',
      description: 'Your data.',
      path: '/privacy',
    });

    leaving.unmount();

    expect(content('meta[name="description"]')).toBe('Your data.');
    expect(canonical()).toBe(`${siteUrl()}/privacy`);

    entering.unmount();

    expect(content('meta[name="description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(canonical()).toBeUndefined();
  });
});
