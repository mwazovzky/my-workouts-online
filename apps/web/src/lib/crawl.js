/**
 * Paths search engines may index. The sitemap lists exactly these; pages
 * prerendered or published later are added here so the two never drift.
 */
export const PUBLIC_PATHS = ['/', '/privacy', '/support'];

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
