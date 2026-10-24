import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import catalog from 'virtual:public-catalog';
import CatalogProgram from '@/components/CatalogProgram.vue';
import { useLocaleStore } from '@/stores/locale';
import { RouterLinkStub, withPinia } from '@/test/utils';

const program = catalog.programs.find(candidate => candidate.slug === 'gym-full-body-a-b');

function build(pinia) {
  return mount(CatalogProgram, {
    props: { program },
    global: {
      plugins: [pinia],
      stubs: { RouterLink: RouterLinkStub, PublicPageLayout: { template: '<div><slot /></div>' } },
    },
  });
}

describe('CatalogProgram', () => {
  afterEach(() => {
    document.head.innerHTML = '';
  });

  it('shows the schedule, each workout once with its exercises and sets, and the equipment', () => {
    const wrapper = build(withPinia());
    const workouts = wrapper.findAll('section[id]');

    expect(wrapper.find('h1').text()).toBe(program.name.en);
    expect(wrapper.findAll('ul li')).toHaveLength(program.schedule.length);
    expect(workouts.map(section => section.attributes('id'))).toEqual([
      'gym-full-body-a',
      'gym-full-body-b',
    ]);
    expect(wrapper.find('a[href="#gym-full-body-b"]').text()).toBe('Full Body B');
    expect(workouts[0].text()).toContain('Leg Press');
    expect(workouts[0].text()).toMatch(/3 × 10 reps/);
    expect(wrapper.find('dl').text()).toContain('Leg Press Machine');
  });

  it('asks a guest to create an account', () => {
    const wrapper = build(withPinia());

    expect(wrapper.find('a[href="/register"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/programs.index"]').exists()).toBe(false);
  });

  it('sends a signed-in user to Programs in the app', () => {
    const wrapper = build(withPinia({ id: 1, name: 'Ann' }));

    expect(wrapper.find('a[href="/programs.index"]').exists()).toBe(true);
    expect(wrapper.find('a[href="/register"]').exists()).toBe(false);
  });

  it('renders in Russian with Russian links', () => {
    const pinia = withPinia();
    useLocaleStore().setLocale('ru', { persist: false });
    const wrapper = build(pinia);

    expect(wrapper.find('h1').text()).toBe(program.name.ru);
    expect(wrapper.text()).toContain('Понедельник');
    expect(wrapper.find('a[href="/ru/catalog/programs"]').exists()).toBe(true);
  });

  it('sets the page head, with JSON-LD', () => {
    build(withPinia());

    expect(document.title).toBe(`${program.name.en} – My Workouts Online`);
    expect(document.head.querySelector('link[rel="canonical"]').getAttribute('href')).toBe(
      `${window.location.origin}/catalog/programs/gym-full-body-a-b`
    );
    expect(
      JSON.parse(document.head.querySelector('script[data-schema="page"]').textContent)
    ).toMatchObject({ '@type': 'CreativeWork', name: program.name.en, inLanguage: 'en' });
  });
});
