import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { cwd } from 'node:process';
import { describe, expect, it } from 'vitest';
import { buildRobots, buildSitemap, PUBLIC_PATHS, publicPagePath } from '@/lib/crawl';
import { routes } from '@/router/routes';

describe('buildSitemap', () => {
  it('lists every public path as an absolute URL', () => {
    const xml = buildSitemap('https://my-workouts.online/');
    const locs = [...xml.matchAll(/<loc>(.*?)<\/loc>/g)].map(match => match[1]);

    expect(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')).toBe(true);
    expect(xml).toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    expect(locs).toEqual([
      'https://my-workouts.online/',
      'https://my-workouts.online/ru',
      'https://my-workouts.online/privacy',
      'https://my-workouts.online/ru/privacy',
      'https://my-workouts.online/support',
      'https://my-workouts.online/ru/support',
    ]);
  });

  it('escapes XML characters in URLs', () => {
    expect(buildSitemap('https://example.test', ['/a?b=1&c=2'])).toContain(
      '<loc>https://example.test/a?b=1&amp;c=2</loc>'
    );
  });
});

describe('publicPagePath', () => {
  it('returns the page in a language, falling back to English', () => {
    expect(publicPagePath('privacy', 'ru')).toBe('/ru/privacy');
    expect(publicPagePath('welcome', 'en')).toBe('/');
    expect(publicPagePath('support', 'de')).toBe('/support');
  });
});

describe('buildRobots', () => {
  it('allows every crawler and names the sitemap', () => {
    expect(buildRobots('https://my-workouts.online')).toBe(
      'User-agent: *\nDisallow:\n\nSitemap: https://my-workouts.online/sitemap.xml\n'
    );
  });
});

describe('nginx noindex map', () => {
  const config = readFileSync(resolve(cwd(), '../../docker/nginx/robots-map.conf'), 'utf8');
  const noindex = new RegExp(config.match(/"~(.+)" "noindex";/)[1]);

  const privatePaths = routes
    .filter(route => route.name !== 'not-found' && !PUBLIC_PATHS.includes(route.path))
    .map(route => route.path.replace(/:[^/]+/g, '1'));

  it('covers every signed-in and auth route', () => {
    expect(privatePaths.length).toBeGreaterThan(10);
    expect(privatePaths.filter(path => !noindex.test(path))).toEqual([]);
    expect(noindex.test('/login?redirect=%2Fdashboard')).toBe(true);
  });

  it('leaves public pages, assets and the API indexable', () => {
    const indexable = [
      ...PUBLIC_PATHS,
      '/sitemap.xml',
      '/assets/index.js',
      '/api/v1/meta',
      '/catalog/programs/gym-full-body-a-b',
      '/loginx',
    ];

    expect(indexable.filter(path => noindex.test(path))).toEqual([]);
  });
});
