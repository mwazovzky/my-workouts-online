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
  '/catalog/exercises': 'Exercise catalog',
  '/ru/catalog/exercises': 'Каталог упражнений',
};

const localeOf = path => (path === '/ru' || path.startsWith('/ru/') ? 'ru' : 'en');
const englishOf = path => (localeOf(path) === 'ru' ? path.replace(/^\/ru/, '') || '/' : path);
const programOf = path =>
  catalog.programs.find(program => englishOf(path) === `/catalog/programs/${program.slug}`);
const exerciseOf = path =>
  catalog.exercises[englishOf(path).match(/^\/catalog\/exercises\/(.+)$/)?.[1]];
const escapeHtml = value =>
  value.replace(
    /[&<>"']/g,
    char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]
  );
const heading = html => html.match(/<h1[\s\S]*?<\/h1>/)[0];

const tag = (head, selector) => head.tags.find(candidate => candidate.selector === selector);
const href = (head, selector) => tag(head, selector).attributes.href;

describe('render (prerendering)', () => {
  it('prerenders the fixed pages and two pages per shipped program and exercise', () => {
    const exercises = Object.keys(catalog.exercises).length;

    expect(catalog.programs.length).toBeGreaterThan(0);
    expect(exercises).toBeGreaterThanOrEqual(137);
    expect(PRERENDER_PATHS).toHaveLength(
      PUBLIC_PATHS.length + 4 + (catalog.programs.length + exercises) * 2
    );
    expect(new Set(PRERENDER_PATHS).size).toBe(PRERENDER_PATHS.length);
  });

  it.each(PRERENDER_PATHS)('renders %s with its heading and head', async path => {
    const page = await render(path);
    const locale = localeOf(path);
    const record = programOf(path) ?? exerciseOf(path);

    expect(page.path).toBe(path);
    expect(page.locale).toBe(locale);
    expect(heading(page.html)).toContain(record ? escapeHtml(record.name[locale]) : HEADINGS[path]);
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

  it('renders an exercise page with its description, equipment, categories, programs and related exercises', async () => {
    const page = await render('/catalog/exercises/leg-press');

    expect(page.html).toContain(escapeHtml(catalog.exercises['leg-press'].description.en));
    expect(page.html).toContain('Leg Press Machine');
    expect(page.html).toContain('Legs');
    expect(page.html).toContain('Repetitions');
    expect(page.html).toContain('href="/catalog/programs/gym-full-body-a-b"');
    expect(page.html).toContain('Related exercises');
    expect(page.html).toContain('href="/register"');
    expect(page.head.title).toBe('Leg Press: how to do it – My Workouts Online');
    expect(
      JSON.parse(tag(page.head, 'script[type="application/ld+json"][data-schema="page"]').text)
    ).toMatchObject({
      '@type': 'WebPage',
      about: { '@type': 'Thing', name: 'Leg Press' },
      inLanguage: 'en',
    });
  });

  it('renders the Russian exercise page and index in Russian', async () => {
    const page = await render('/ru/catalog/exercises/plank');
    const index = await render('/ru/catalog/exercises');

    expect(page.html).toContain('Техника выполнения');
    expect(page.html).toContain('Время');
    expect(page.html).toContain(escapeHtml(catalog.exercises.plank.description.ru));
    expect(index.html).toContain('href="/ru/catalog/exercises/plank"');
    expect(index.html).not.toContain('role="search"');
  });

  it('links exercise names on program pages', async () => {
    const page = await render('/ru/catalog/programs/gym-full-body-a-b');

    expect(page.html).toContain('href="/ru/catalog/exercises/leg-press"');
  });

  it('gives every page in a language its own title and description', async () => {
    // One at a time, like prerender.js: renders share the active Pinia.
    const pages = [];
    for (const path of PRERENDER_PATHS) {
      pages.push(await render(path));
    }

    for (const locale of ['en', 'ru']) {
      const heads = pages.filter(page => page.locale === locale).map(page => page.head);
      const description = head => tag(head, 'meta[name="description"]').attributes.content;

      expect(new Set(heads.map(head => head.title)).size).toBe(heads.length);
      expect(new Set(heads.map(description)).size).toBe(heads.length);
    }
  });
});
