/**
 * Public pages by route name, with their path in each language. The build
 * prerenders exactly these, the sitemap lists them, and each page's
 * `hreflang` links point at its other versions; English is the default.
 */
export const PUBLIC_PAGES = {
  welcome: { en: '/', ru: '/ru' },
  privacy: { en: '/privacy', ru: '/ru/privacy' },
  support: { en: '/support', ru: '/ru/support' },
};

export const DEFAULT_LOCALE = 'en';

/**
 * Paths search engines may index: every language version of every public page.
 */
export const PUBLIC_PATHS = Object.values(PUBLIC_PAGES).flatMap(paths => Object.values(paths));

/**
 * The path of a public page in a language, falling back to the default one.
 */
export function publicPagePath(page, locale) {
  const paths = PUBLIC_PAGES[page];

  return paths[locale] ?? paths[DEFAULT_LOCALE];
}

/**
 * The origin used when `VITE_PUBLIC_URL` is not set (dev and CI builds).
 */
export const DEV_SITE_URL = 'http://localhost:8080';

/**
 * The site origin without a trailing slash, e.g. `https://my-workouts.online`.
 */
export function normalizeSiteUrl(siteUrl) {
  return siteUrl.replace(/\/+$/, '');
}

const escapeXml = value =>
  value.replace(
    /[&<>"']/g,
    char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[char]
  );

/**
 * `sitemap.xml` listing every public path as an absolute URL.
 */
export function buildSitemap(siteUrl, paths = PUBLIC_PATHS) {
  const origin = normalizeSiteUrl(siteUrl);
  const urls = paths.map(path => `  <url><loc>${escapeXml(origin + path)}</loc></url>`);

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls,
    '</urlset>',
    '',
  ].join('\n');
}

/**
 * `robots.txt` allowing every crawler and naming the sitemap. Signed-in pages
 * are not disallowed here: nginx marks them `noindex`, which a crawler only
 * sees if it may fetch them.
 */
export function buildRobots(siteUrl) {
  return [
    'User-agent: *',
    'Disallow:',
    '',
    `Sitemap: ${normalizeSiteUrl(siteUrl)}/sitemap.xml`,
    '',
  ].join('\n');
}
