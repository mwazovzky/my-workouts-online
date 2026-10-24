import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { applyHeadTag, pageHead, resetHead, siteUrl, usePageMeta } from '@/composables/usePageMeta';
import { useLocaleStore } from '@/stores/locale';
import { withPinia } from '@/test/utils';

const DEFAULT_DESCRIPTION = 'Default description.';
const DEFAULT_TITLE = 'Default title';

const content = selector => document.head.querySelector(selector)?.getAttribute('content');
const href = selector => document.head.querySelector(selector)?.getAttribute('href');
const canonical = () => href('link[rel="canonical"]');

function mountPage(meta, pinia = withPinia()) {
  return mount(
    defineComponent({
      setup() {
        usePageMeta(meta);
        return () => h('div');
      },
    }),
    { global: { plugins: [pinia] } }
  );
}

describe('usePageMeta', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.head.innerHTML = `
      <meta name="description" content="${DEFAULT_DESCRIPTION}">
      <meta property="og:title" content="${DEFAULT_TITLE}">
      <meta property="og:description" content="${DEFAULT_DESCRIPTION}">`;
  });

  afterEach(() => {
    document.head.innerHTML = '';
  });

  it('sets the title, description, canonical URL, hreflang and Open Graph tags', () => {
    mountPage({ title: 'Support', description: 'Get help.', page: 'support' });

    expect(document.title).toBe('Support – My Workouts Online');
    expect(content('meta[name="description"]')).toBe('Get help.');
    expect(content('meta[property="og:title"]')).toBe('Support – My Workouts Online');
    expect(content('meta[property="og:description"]')).toBe('Get help.');
    expect(content('meta[property="og:url"]')).toBe(`${siteUrl()}/support`);
    expect(content('meta[property="og:locale"]')).toBe('en_US');
    expect(canonical()).toBe(`${siteUrl()}/support`);
    expect(href('link[hreflang="en"]')).toBe(`${siteUrl()}/support`);
    expect(href('link[hreflang="ru"]')).toBe(`${siteUrl()}/ru/support`);
    expect(href('link[hreflang="x-default"]')).toBe(`${siteUrl()}/support`);
    expect(document.head.querySelectorAll('link[rel="canonical"]')).toHaveLength(1);
  });

  it('follows the language and reactive values', async () => {
    const pinia = withPinia();
    const wrapper = mountPage(
      {
        title: () => (useLocaleStore().locale === 'ru' ? 'Поддержка' : 'Support'),
        description: () => (useLocaleStore().locale === 'ru' ? 'Помощь.' : 'Get help.'),
        page: 'support',
      },
      pinia
    );

    useLocaleStore().setLocale('ru', { persist: false });
    await wrapper.vm.$nextTick();

    expect(content('meta[name="description"]')).toBe('Помощь.');
    expect(content('meta[property="og:title"]')).toBe('Поддержка – My Workouts Online');
    expect(content('meta[property="og:locale"]')).toBe('ru_RU');
    expect(canonical()).toBe(`${siteUrl()}/ru/support`);
    expect(href('link[hreflang="x-default"]')).toBe(`${siteUrl()}/support`);
  });

  it('restores the defaults and removes the tags it added on unmount', () => {
    const wrapper = mountPage({ title: 'Support', description: 'Get help.', page: 'support' });

    wrapper.unmount();

    expect(content('meta[name="description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(content('meta[property="og:title"]')).toBe(DEFAULT_TITLE);
    expect(content('meta[property="og:description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(content('meta[property="og:url"]')).toBeUndefined();
    expect(content('meta[property="og:locale"]')).toBeUndefined();
    expect(canonical()).toBeUndefined();
    expect(document.head.querySelectorAll('link[rel="alternate"]')).toHaveLength(0);
  });

  it('takes over tags written by the prerender step and removes them on leaving', () => {
    document.head.innerHTML = `
      <meta name="description" content="Prerendered." data-default="${DEFAULT_DESCRIPTION}">
      <link data-page-meta="created" rel="canonical" href="${siteUrl()}/privacy">`;

    const wrapper = mountPage({ title: 'Privacy', description: 'Your data.', page: 'privacy' });

    expect(document.head.querySelectorAll('link[rel="canonical"]')).toHaveLength(1);

    wrapper.unmount();

    expect(content('meta[name="description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(canonical()).toBeUndefined();
  });

  it('leaves the tags of the next page alone when the previous page unmounts late', () => {
    const leaving = mountPage({ title: 'Support', description: 'Get help.', page: 'support' });
    const entering = mountPage({ title: 'Privacy', description: 'Your data.', page: 'privacy' });

    leaving.unmount();

    expect(content('meta[name="description"]')).toBe('Your data.');
    expect(canonical()).toBe(`${siteUrl()}/privacy`);

    entering.unmount();

    expect(content('meta[name="description"]')).toBe(DEFAULT_DESCRIPTION);
    expect(canonical()).toBeUndefined();
  });
});

describe('pageHead and applyHeadTag', () => {
  it('builds the same tags for the prerender step', () => {
    const head = pageHead({
      title: 'Главная',
      description: 'Описание.',
      page: 'welcome',
      locale: 'ru',
    });
    const document = new window.DOMParser().parseFromString(
      `<html><head><meta name="description" content="${DEFAULT_DESCRIPTION}"></head></html>`,
      'text/html'
    );

    head.tags.forEach(tag => applyHeadTag(document, tag));

    expect(head.title).toBe('Главная – My Workouts Online');
    expect(document.head.querySelector('meta[name="description"]').outerHTML).toContain(
      `data-default="${DEFAULT_DESCRIPTION}"`
    );
    expect(document.head.querySelector('link[rel="canonical"]').getAttribute('href')).toBe(
      `${siteUrl()}/ru`
    );
    expect(document.head.querySelector('link[rel="canonical"]').dataset.pageMeta).toBe('created');
  });
});

describe('resetHead', () => {
  it('removes a prerendered page head and restores the defaults, title included', () => {
    const document = new window.DOMParser().parseFromString(
      `<html><head>
        <title data-default="Default title">Privacy Policy – My Workouts Online</title>
        <meta name="description" content="Your data." data-default="${DEFAULT_DESCRIPTION}">
        <link data-page-meta="created" rel="canonical" href="https://example.test/privacy">
        <link data-page-meta="created" rel="alternate" hreflang="ru" href="https://example.test/ru/privacy">
      </head></html>`,
      'text/html'
    );

    resetHead(document);

    expect(document.title).toBe('Default title');
    expect(document.head.querySelector('meta[name="description"]').getAttribute('content')).toBe(
      DEFAULT_DESCRIPTION
    );
    expect(document.head.querySelectorAll('link')).toHaveLength(0);
  });
});
