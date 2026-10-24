import { onUnmounted, toValue, useSSRContext, watchEffect } from 'vue';
import { fullPageTitle, usePageTitle } from '@/composables/usePageTitle';
import { APP_NAME } from '@/composables/usePageTitle';
import {
  DEFAULT_LOCALE,
  DEV_SITE_URL,
  localizedPaths,
  normalizeSiteUrl,
  PUBLIC_PAGES,
} from '@/lib/crawl';
import { useLocaleStore } from '@/stores/locale';

const OG_LOCALES = { en: 'en_US', ru: 'ru_RU' };

/**
 * The public origin for absolute URLs: the build's `VITE_PUBLIC_URL`, or the
 * current origin when it is not set (dev and tests; the dev origin when
 * prerendering).
 */
export function siteUrl() {
  const configured = import.meta.env.VITE_PUBLIC_URL;

  if (configured) {
    return normalizeSiteUrl(configured);
  }

  return import.meta.env.SSR ? DEV_SITE_URL : window.location.origin;
}

const meta = (key, name, content) => ({
  selector: `meta[${key}="${name}"]`,
  tag: 'meta',
  attributes: { [key]: name, content },
});

const alternate = (hreflang, href) => ({
  selector: `link[rel="alternate"][hreflang="${hreflang}"]`,
  tag: 'link',
  attributes: { rel: 'alternate', hreflang, href },
});

// Inside a <script>, "</script>" in a value would end the element early.
const scriptJson = data => JSON.stringify(data).replaceAll('<', '\\u003c');

/**
 * The full title and head tags of a public page in a language: description,
 * canonical URL, Open Graph, an `hreflang` link per language plus
 * `x-default`, and optional JSON-LD. The page is `page`, a key of
 * `PUBLIC_PAGES`, or `path`, its English path (`src/lib/crawl.js`).
 * `structuredData` is a schema.org object; its `url`, `inLanguage` and the
 * site it belongs to are added.
 */
export function pageHead({ title, description, page, path, locale, structuredData }) {
  const origin = siteUrl();
  const paths = page ? PUBLIC_PAGES[page] : localizedPaths(path);
  const url = origin + (paths[locale] ?? paths[DEFAULT_LOCALE]);
  const fullTitle = fullPageTitle(title);
  const schema = structuredData && {
    tag: 'script',
    selector: 'script[type="application/ld+json"][data-schema="page"]',
    attributes: { type: 'application/ld+json', 'data-schema': 'page' },
    text: scriptJson({
      '@context': 'https://schema.org',
      ...structuredData,
      url,
      inLanguage: locale,
      isPartOf: { '@type': 'WebSite', name: APP_NAME, url: `${origin}/` },
    }),
  };

  return {
    title: fullTitle,
    tags: [
      meta('name', 'description', description),
      meta('property', 'og:title', fullTitle),
      meta('property', 'og:description', description),
      meta('property', 'og:url', url),
      meta('property', 'og:locale', OG_LOCALES[locale] ?? OG_LOCALES[DEFAULT_LOCALE]),
      {
        selector: 'link[rel="canonical"]',
        tag: 'link',
        attributes: { rel: 'canonical', href: url },
      },
      ...Object.entries(paths).map(([language, path]) => alternate(language, origin + path)),
      alternate('x-default', origin + paths[DEFAULT_LOCALE]),
      ...(schema ? [schema] : []),
    ],
  };
}

const valueAttribute = element => (element.tagName === 'LINK' ? 'href' : 'content');

/**
 * Applies a head tag to `document`. An element `index.html` already has keeps
 * its original value in `data-default`; a missing one is created and marked
 * `data-page-meta="created"`. Used in the browser and by the prerender step.
 */
export function applyHeadTag(document, { selector, tag, attributes, text }) {
  let element = document.head.querySelector(selector);

  if (!element) {
    element = document.createElement(tag);
    element.setAttribute('data-page-meta', 'created');
    document.head.appendChild(element);
  } else if (!element.hasAttribute('data-default') && !element.hasAttribute('data-page-meta')) {
    element.setAttribute('data-default', element.getAttribute(valueAttribute(element)) ?? '');
  }

  Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
  if (text !== undefined) {
    element.textContent = text;
  }

  return element;
}

/**
 * Puts the head back to the `index.html` defaults: removes the tags a page
 * added and restores the values it changed, title included. Used when the app
 * renders something other than the page prerendered into the HTML.
 */
export function resetHead(document) {
  document.head.querySelectorAll('[data-page-meta="created"]').forEach(element => element.remove());
  document.head.querySelectorAll('[data-default]').forEach(element => {
    if (element.tagName === 'TITLE') {
      element.textContent = element.getAttribute('data-default');
    } else {
      element.setAttribute(valueAttribute(element), element.getAttribute('data-default'));
    }
  });
}

/**
 * Sets the title, description, canonical URL, `hreflang` and Open Graph tags
 * of a public page in the current language, and its JSON-LD when given;
 * title, description and `structuredData` accept values, refs or getters. The
 * page is `page`, a key of `PUBLIC_PAGES`, or `path`, its English path. On
 * unmount the tags it changed go back to the `index.html` defaults and the
 * ones it added are removed, unless another page has claimed them since. When
 * prerendering, the head is handed to the prerender step through the SSR
 * context instead.
 */
export function usePageMeta({ title, description, page, path, structuredData }) {
  const localeStore = useLocaleStore();
  const head = () =>
    pageHead({
      title: toValue(title),
      description: toValue(description),
      page,
      path: toValue(path),
      locale: localeStore.locale,
      structuredData: toValue(structuredData),
    });

  if (import.meta.env.SSR) {
    const context = useSSRContext();
    if (context) {
      context.head = head();
    }
    return;
  }

  const owner = Symbol('usePageMeta');
  const touched = new Set();

  usePageTitle(title);

  watchEffect(() => {
    for (const tag of head().tags) {
      const element = applyHeadTag(document, tag);
      element.pageMetaOwner = owner;
      touched.add(element);
    }
  });

  onUnmounted(() => {
    for (const element of touched) {
      if (element.pageMetaOwner !== owner) {
        continue;
      }

      if (element.getAttribute('data-page-meta') === 'created') {
        element.remove();
      } else {
        element.setAttribute(valueAttribute(element), element.getAttribute('data-default'));
        delete element.pageMetaOwner;
      }
    }
  });
}
