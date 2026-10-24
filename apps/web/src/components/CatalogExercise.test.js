import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import catalog from 'virtual:public-catalog';
import CatalogExercise from '@/components/CatalogExercise.vue';
import ExerciseIndex from '@/pages/Catalog/ExerciseIndex.vue';
import { useLocaleStore } from '@/stores/locale';
import { RouterLinkStub, withPinia } from '@/test/utils';

const stubs = { RouterLink: RouterLinkStub, PublicPageLayout: { template: '<div><slot /></div>' } };

function build(slug, pinia = withPinia()) {
  return mount(CatalogExercise, { props: { slug }, global: { plugins: [pinia], stubs } });
}

describe('CatalogExercise', () => {
  afterEach(() => {
    document.head.innerHTML = '';
  });

  it('shows the description, equipment, categories, measure, programs and related exercises', () => {
    const exercise = catalog.exercises['leg-press'];
    const wrapper = build('leg-press');

    expect(wrapper.find('h1').text()).toBe('Leg Press');
    expect(wrapper.text()).toContain(exercise.description.en);
    expect(wrapper.find('dl').text()).toContain('Leg Press Machine');
    expect(wrapper.find('dl').text()).toContain('Repetitions');
    expect(wrapper.find('a[href="/catalog/programs/gym-full-body-a-b"]').exists()).toBe(true);
    expect(wrapper.findAll('a[href^="/catalog/exercises/"]').length).toBeGreaterThan(0);
    expect(wrapper.find('a[href="/catalog/exercises/leg-press"]').exists()).toBe(false);
    expect(wrapper.find('a[href="/register"]').exists()).toBe(true);
  });

  it('leaves the sign-up box out for a signed-in user', () => {
    const wrapper = build('leg-press', withPinia({ id: 1, name: 'Ann' }));

    expect(wrapper.find('a[href="/register"]').exists()).toBe(false);
  });

  it('renders in Russian with Russian links', () => {
    const pinia = withPinia();
    useLocaleStore().setLocale('ru', { persist: false });
    const wrapper = build('plank', pinia);

    expect(wrapper.find('h1').text()).toBe(catalog.exercises.plank.name.ru);
    expect(wrapper.find('dl').text()).toContain('Время');
    expect(wrapper.find('a[href="/ru/catalog/exercises"]').exists()).toBe(true);
    expect(document.title).toBe(
      `${catalog.exercises.plank.name.ru}: техника выполнения – My Workouts Online`
    );
  });

  it('describes the page as a WebPage about the exercise', () => {
    build('plank');

    expect(
      JSON.parse(document.head.querySelector('script[data-schema="page"]').textContent)
    ).toMatchObject({ '@type': 'WebPage', about: { '@type': 'Thing', name: 'Plank' } });
  });
});

describe('ExerciseIndex', () => {
  const mountIndex = () => mount(ExerciseIndex, { global: { plugins: [withPinia()], stubs } });
  const visibleNames = wrapper =>
    wrapper
      .findAll('li')
      .filter(item => !(item.attributes('style') ?? '').includes('display: none'))
      .map(item => item.find('a').text());

  afterEach(() => {
    document.head.innerHTML = '';
  });

  it('lists every exercise once, grouped by main category', async () => {
    const wrapper = mountIndex();
    await flushPromises();

    expect(wrapper.findAll('li')).toHaveLength(Object.keys(catalog.exercises).length);
    expect(wrapper.findAll('h2').map(heading => heading.text())).toContain('Legs');
  });

  it('filters by name and equipment once loaded', async () => {
    const wrapper = mountIndex();
    await flushPromises();

    await wrapper.find('input').setValue('press');
    expect(visibleNames(wrapper).length).toBeGreaterThan(1);
    expect(visibleNames(wrapper).every(name => name.toLowerCase().includes('press'))).toBe(true);

    await wrapper.find('select').setValue('leg-press-machine');
    expect(visibleNames(wrapper)).toEqual(['Leg Press']);

    await wrapper.find('input').setValue('no such exercise');
    expect(wrapper.text()).toContain('No exercises match the filter.');
  });
});
