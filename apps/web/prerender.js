// Prerenders the public pages after `vite build` and the SSR build of
// src/entry-server.js (see the `build` script). Each page becomes
// dist/<path>/index.html with its own head and markup; the untouched shell is
// kept as dist/spa.html, which nginx serves for every other route. Fails the
// build if a page redirects, sets no head, two pages in one language share a
// title or a description, or the pages and the sitemap differ.
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { Window } from 'happy-dom';

const DIST = 'dist';
const SSR_BUILD = '.ssr';
const APP_ROOT = '<div id="app"></div>';

const { render, applyHeadTag, PRERENDER_PATHS } = await import(`./${SSR_BUILD}/entry-server.js`);

const shell = readFileSync(join(DIST, 'index.html'), 'utf8');
const sitemap = readFileSync(join(DIST, 'sitemap.xml'), 'utf8');
const sitemapUrls = [...sitemap.matchAll(/<loc>(.*?)<\/loc>/g)].map(match => match[1]);

if (shell.split(APP_ROOT).length !== 2) {
  throw new Error(`dist/index.html must contain ${APP_ROOT} exactly once`);
}

const window = new Window({
  settings: {
    disableJavaScriptEvaluation: true,
    disableJavaScriptFileLoading: true,
    disableCSSFileLoading: true,
  },
});

/**
 * The shell with the page's language, title and head tags.
 */
function pageShell({ head, locale }) {
  const document = new window.DOMParser().parseFromString(shell, 'text/html');
  const title = document.querySelector('title');

  document.documentElement.setAttribute('lang', locale);
  title.setAttribute('data-default', title.textContent);
  title.textContent = head.title;
  head.tags.forEach(tag => applyHeadTag(document, tag));

  return `<!doctype html>\n${document.documentElement.outerHTML}\n`;
}

const pages = [];

for (const path of PRERENDER_PATHS) {
  const page = await render(path);

  if (page.path !== path) {
    throw new Error(`${path} rendered ${page.path} instead`);
  }
  if (!page.head) {
    throw new Error(`${path} sets no head; public pages must call usePageMeta`);
  }
  if (!/<h1[\s>]/.test(page.html)) {
    throw new Error(`${path} has no <h1>`);
  }

  const canonical = page.head.tags.find(tag => tag.selector === 'link[rel="canonical"]');
  const html = pageShell(page).replace(
    APP_ROOT,
    `<div id="app" data-prerendered="${path}">${page.html}</div>`
  );
  const file = join(DIST, path, 'index.html');

  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, html);
  const description = page.head.tags.find(tag => tag.selector === 'meta[name="description"]');
  pages.push({
    path,
    locale: page.locale,
    url: canonical.attributes.href,
    file,
    title: page.head.title,
    description: description.attributes.content,
  });
}

await window.happyDOM.close();

writeFileSync(join(DIST, 'spa.html'), shell);
rmSync(SSR_BUILD, { recursive: true, force: true });

const pageUrls = pages.map(page => page.url);
const missing = sitemapUrls.filter(url => !pageUrls.includes(url));
const unlisted = pageUrls.filter(url => !sitemapUrls.includes(url));

if (missing.length || unlisted.length) {
  throw new Error(
    `The sitemap and the prerendered pages differ. In the sitemap but not prerendered: ${
      missing.join(', ') || 'none'
    }. Prerendered but not in the sitemap: ${unlisted.join(', ') || 'none'}.`
  );
}

for (const field of ['title', 'description']) {
  const seen = new Map();

  for (const page of pages) {
    const key = `${page.locale}:${page[field]}`;

    if (seen.has(key)) {
      throw new Error(`${seen.get(key)} and ${page.path} have the same ${field}`);
    }
    seen.set(key, page.path);
  }
}

console.log(`prerendered ${pages.length} pages into ${DIST}/`);
