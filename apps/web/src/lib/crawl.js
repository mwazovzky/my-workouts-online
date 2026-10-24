export const DEFAULT_LOCALE = 'en';

/**
 * Languages every public page exists in: English unprefixed, the others under
 * `/{locale}`.
 */
export const LOCALES = ['en', 'ru'];

/**
 * The path of a public page in a language, from its English path:
 * `localizedPath('/privacy', 'ru')` is `/ru/privacy`, `localizedPath('/', 'ru')`
 * is `/ru`.
 */
export function localizedPath(path, locale) {
  if (locale === DEFAULT_LOCALE || !LOCALES.includes(locale)) {
    return path;
  }

  return `/${locale}${path === '/' ? '' : path}`;
}

/**
 * Every language version of a public page, by locale.
 */
export function localizedPaths(path) {
  return Object.fromEntries(LOCALES.map(locale => [locale, localizedPath(path, locale)]));
}

/**
 * The same public page in another language: `/ru/privacy` to `/privacy`.
 */
export function switchLocalePath(path, locale) {
  const prefix = LOCALES.find(
    candidate =>
      candidate !== DEFAULT_LOCALE &&
      (path === `/${candidate}` || path.startsWith(`/${candidate}/`))
  );
  const english = prefix ? path.slice(prefix.length + 1) || '/' : path;

  return localizedPath(english, locale);
}

/**
 * Public pages by route name, with their path in each language. The build
 * prerenders these and the program and exercise catalogs (`catalogPaths`),
 * the sitemap lists them, and each page's `hreflang` links point at its other
 * versions; English is the default.
 */
export const PUBLIC_PAGES = {
  welcome: localizedPaths('/'),
  privacy: localizedPaths('/privacy'),
  support: localizedPaths('/support'),
};

/**
 * Paths of the fixed public pages in every language.
 */
export const PUBLIC_PATHS = Object.values(PUBLIC_PAGES).flatMap(paths => Object.values(paths));

/**
 * The path of a public page in a language, falling back to the default one.
 */
export function publicPagePath(page, locale) {
  const paths = PUBLIC_PAGES[page];

  return paths[locale] ?? paths[DEFAULT_LOCALE];
}

export const PROGRAM_CATALOG_PATH = '/catalog/programs';
export const EXERCISE_CATALOG_PATH = '/catalog/exercises';

/**
 * The English path of a program's catalog page.
 */
export const catalogProgramPath = slug => `${PROGRAM_CATALOG_PATH}/${slug}`;

/**
 * The English path of an exercise's catalog page.
 */
export const catalogExercisePath = slug => `${EXERCISE_CATALOG_PATH}/${slug}`;

/**
 * Every language version of the program and exercise catalogs and of each
 * program and exercise page in `catalog` (`src/lib/publicCatalog.js`).
 */
export function catalogPaths(catalog) {
  return [
    PROGRAM_CATALOG_PATH,
    ...catalog.programs.map(program => catalogProgramPath(program.slug)),
    EXERCISE_CATALOG_PATH,
    ...Object.keys(catalog.exercises ?? {}).map(catalogExercisePath),
  ].flatMap(path => Object.values(localizedPaths(path)));
}

/**
 * Paths search engines may index: the fixed public pages and the catalog.
 */
export function publicPaths(catalog) {
  return [...PUBLIC_PATHS, ...catalogPaths(catalog)];
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
