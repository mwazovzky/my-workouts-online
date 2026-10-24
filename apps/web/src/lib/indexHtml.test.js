import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { cwd } from 'node:process';
import { describe, expect, it } from 'vitest';

const html = readFileSync(resolve(cwd(), 'index.html'), 'utf8');
const head = new window.DOMParser().parseFromString(html, 'text/html').head;
const content = selector => head.querySelector(selector)?.getAttribute('content');

describe('index.html head defaults', () => {
  it('names the product in the title and describes it', () => {
    expect(head.querySelector('title').textContent).toBe(
      'Workout Tracker & Training Programs – My Workouts Online'
    );
    expect(content('meta[name="description"]').length).toBeGreaterThan(70);
    expect(content('meta[name="description"]').length).toBeLessThanOrEqual(160);
  });

  it('has the Open Graph and Twitter card tags with an absolute image URL', () => {
    expect(content('meta[property="og:type"]')).toBe('website');
    expect(content('meta[property="og:site_name"]')).toBe('My Workouts Online');
    expect(content('meta[property="og:title"]')).toBe(head.querySelector('title').textContent);
    expect(content('meta[property="og:description"]')).toBe(content('meta[name="description"]'));
    expect(content('meta[property="og:image"]')).toBe('__SITE_URL__/og-image.png');
    expect(content('meta[name="twitter:card"]')).toBe('summary_large_image');
  });

  it('has no canonical URL, which each public page sets for itself', () => {
    expect(head.querySelector('link[rel="canonical"]')).toBeNull();
    expect(head.querySelector('meta[property="og:url"]')).toBeNull();
  });

  it('has one JSON-LD block describing the website and its publisher', () => {
    const blocks = head.querySelectorAll('script[type="application/ld+json"]');

    expect(blocks).toHaveLength(1);

    const graph = JSON.parse(blocks[0].textContent)['@graph'];
    const byType = Object.fromEntries(graph.map(node => [node['@type'], node]));

    expect(byType.WebSite).toMatchObject({ name: 'My Workouts Online', url: '__SITE_URL__/' });
    expect(byType.WebSite.publisher['@id']).toBe(byType.Organization['@id']);
    expect(byType.Organization).toMatchObject({
      name: 'My Workouts Online',
      email: 'support@my-workouts.online',
      logo: '__SITE_URL__/apple-touch-icon.png',
    });
  });
});
