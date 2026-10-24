// @vitest-environment node
// Runs like the prerender step: no window, document or localStorage.
import { describe, expect, it } from 'vitest';
import { PUBLIC_PATHS, render } from '@/entry-server';

const HEADINGS = {
  '/': 'Know what to train next',
  '/ru': 'Понимайте, что тренировать дальше',
  '/privacy': 'Privacy Policy',
  '/ru/privacy': 'Политика конфиденциальности',
  '/support': 'Support',
  '/ru/support': 'Поддержка',
};

const href = (head, selector) => head.tags.find(tag => tag.selector === selector).attributes.href;

describe('render (prerendering)', () => {
  it.each(PUBLIC_PATHS)('renders %s with its heading and head', async path => {
    const page = await render(path);
    const locale = path.startsWith('/ru') ? 'ru' : 'en';
    const english = locale === 'ru' ? path.replace(/^\/ru/, '') || '/' : path;

    expect(page.path).toBe(path);
    expect(page.locale).toBe(locale);
    expect(page.html).toMatch(/<h1[\s>]/);
    expect(page.html).toContain(HEADINGS[path]);
    expect(page.head.title).toMatch(/ – My Workouts Online$/);
    expect(href(page.head, 'link[rel="canonical"]')).toBe(`http://localhost:8080${path}`);
    expect(href(page.head, 'link[rel="alternate"][hreflang="x-default"]')).toBe(
      `http://localhost:8080${english}`
    );
  });

  it('names the product in the landing heading', async () => {
    const page = await render('/');
    const heading = page.html.match(/<h1[\s\S]*?<\/h1>/)[0];

    expect(heading).toContain('My Workouts Online');
  });
});
