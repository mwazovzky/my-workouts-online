// @vitest-environment node
// Runs like the prerender step: no window, document or localStorage.
import { describe, expect, it } from 'vitest';
import catalog from 'virtual:public-catalog';
import { PRERENDER_PATHS, render } from '@/entry-server';
import { PUBLIC_PATHS } from '@/lib/crawl';

const HEADINGS = {
  '/': 'Know what to train next',
  '/ru': 'Понимайте, что тренировать дальше',
  '/privacy': 'Privacy Policy',
  '/ru/privacy': 'Политика конфиденциальности',
  '/support': 'Support',
  '/ru/support': 'Поддержка',
  '/catalog/programs': 'Program catalog',
  '/ru/catalog/programs': 'Каталог программ',
};

const localeOf = path => (path === '/ru' || path.startsWith('/ru/') ? 'ru' : 'en');
const englishOf = path => (localeOf(path) === 'ru' ? path.replace(/^\/ru/, '') || '/' : path);
const programOf = path =>
  catalog.programs.find(program => englishOf(path).endsWith(`/${program.slug}`));
const heading = html => html.match(/<h1[\s\S]*?<\/h1>/)[0];

const tag = (head, selector) => head.tags.find(candidate => candidate.selector === selector);
const href = (head, selector) => tag(head, selector).attributes.href;

describe('render (prerendering)', () => {
  it('prerenders the fixed pages and two pages per shipped program', () => {
    expect(catalog.programs.length).toBeGreaterThan(0);
    expect(PRERENDER_PATHS).toHaveLength(PUBLIC_PATHS.length + 2 + catalog.programs.length * 2);
    expect(new Set(PRERENDER_PATHS).size).toBe(PRERENDER_PATHS.length);
  });

  it.each(PRERENDER_PATHS)('renders %s with its heading and head', async path => {
    const page = await render(path);
    const locale = localeOf(path);
    const program = programOf(path);

    expect(page.path).toBe(path);
    expect(page.locale).toBe(locale);
    expect(heading(page.html)).toContain(
      program ? program.name[locale].replaceAll('&', '&amp;') : HEADINGS[path]
    );
    expect(page.head.title).toMatch(/ – My Workouts Online$/);
    expect(href(page.head, 'link[rel="canonical"]')).toBe(`http://localhost:8080${path}`);
    expect(href(page.head, 'link[rel="alternate"][hreflang="x-default"]')).toBe(
      `http://localhost:8080${englishOf(path)}`
    );
  });

  it('names the product in the landing heading', async () => {
    const page = await render('/');

    expect(heading(page.html)).toContain('My Workouts Online');
  });

  it('renders a program page with its schedule, exercises, sets and the sign-up link', async () => {
    const page = await render('/catalog/programs/gym-full-body-a-b');

    expect(page.html).toContain('Weekly schedule');
    expect(page.html).toContain('Monday');
    expect(page.html).toContain('Full Body A');
    expect(page.html).toContain('Leg Press');
    expect(page.html).toMatch(/\d × \d+ reps/);
    expect(page.html).toContain('href="/register"');
    expect(page.html).toContain('href="/catalog/programs"');
  });

  it('renders the Russian program page in Russian with Russian links', async () => {
    const page = await render('/ru/catalog/programs/cycling-beginner-week-1');

    expect(page.html).toContain('Расписание на неделю');
    expect(page.html).toContain('Понедельник');
    expect(page.html).toMatch(/\d+ мин \(зона \d\)/);
    expect(page.html).toContain('href="/ru/catalog/programs"');
  });

  it('describes a program page as a CreativeWork', async () => {
    const page = await render('/ru/catalog/programs/gym-strength-5x5');
    const schema = tag(page.head, 'script[type="application/ld+json"][data-schema="page"]');
    const data = JSON.parse(schema.text);

    expect(data).toMatchObject({
      '@context': 'https://schema.org',
      '@type': 'CreativeWork',
      name: programOf('/catalog/programs/gym-strength-5x5').name.ru,
      url: 'http://localhost:8080/ru/catalog/programs/gym-strength-5x5',
      inLanguage: 'ru',
      isPartOf: { '@type': 'WebSite', name: 'My Workouts Online' },
    });
  });

  it('renders the not-found page for a slug the catalog does not have', async () => {
    const page = await render('/catalog/programs/no-such-program');

    expect(heading(page.html)).toContain('404');
    expect(page.head).toBeUndefined();
  });
});
