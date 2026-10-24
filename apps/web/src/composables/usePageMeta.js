import { onUnmounted, toValue, watchEffect } from 'vue';
import { fullPageTitle, usePageTitle } from '@/composables/usePageTitle';

/**
 * The public origin for absolute URLs: the build's `VITE_PUBLIC_URL`, or the
 * current origin when it is not set (dev and tests).
 */
export function siteUrl() {
  return (import.meta.env.VITE_PUBLIC_URL || window.location.origin).replace(/\/+$/, '');
}

/**
 * Finds the head element for `selector`, creating it from `attributes` when
 * `index.html` has none.
 */
function headElement(tag, selector, attributes) {
  let element = document.head.querySelector(selector);

  if (!element) {
    element = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
    element.dataset.pageMeta = 'created';
    document.head.appendChild(element);
  }

  return element;
}

/**
 * Sets the title, description, canonical URL and Open Graph tags of a public
 * page; accepts strings, refs or getters. `path` is the page's canonical path
 * (`/privacy`). On unmount the tags it changed go back to the `index.html`
 * defaults and the ones it added are removed, unless another page has
 * claimed them since.
 */
export function usePageMeta({ title, description, path }) {
  const owner = Symbol('usePageMeta');
  const touched = new Set();

  usePageTitle(title);

  const set = (tag, selector, attributes, attribute, value) => {
    const element = headElement(tag, selector, attributes);

    if (!element.hasAttribute('data-default') && element.dataset.pageMeta !== 'created') {
      element.dataset.default = element.getAttribute(attribute) ?? '';
    }

    element.setAttribute(attribute, value);
    element.pageMetaOwner = owner;
    touched.add(element);
  };

  watchEffect(() => {
    const url = `${siteUrl()}${toValue(path)}`;
    const text = toValue(description);

    set('meta', 'meta[name="description"]', { name: 'description' }, 'content', text);
    set(
      'meta',
      'meta[property="og:title"]',
      { property: 'og:title' },
      'content',
      fullPageTitle(toValue(title))
    );
    set('meta', 'meta[property="og:description"]', { property: 'og:description' }, 'content', text);
    set('meta', 'meta[property="og:url"]', { property: 'og:url' }, 'content', url);
    set('link', 'link[rel="canonical"]', { rel: 'canonical' }, 'href', url);
  });

  onUnmounted(() => {
    for (const element of touched) {
      if (element.pageMetaOwner !== owner) {
        continue;
      }

      if (element.dataset.pageMeta === 'created') {
        element.remove();
      } else {
        element.setAttribute(
          element.tagName === 'LINK' ? 'href' : 'content',
          element.dataset.default
        );
        delete element.pageMetaOwner;
      }
    }
  });
}
