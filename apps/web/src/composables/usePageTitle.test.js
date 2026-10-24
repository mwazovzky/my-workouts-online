import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { fullPageTitle, usePageTitle } from '@/composables/usePageTitle';

function mountWith(title) {
  return mount(
    defineComponent({
      setup() {
        usePageTitle(title);
        return () => h('div');
      },
    })
  );
}

describe('usePageTitle', () => {
  it('sets the document title from a string with the brand suffix', () => {
    mountWith('Dashboard');

    expect(document.title).toBe('Dashboard – My Workouts Online');
  });

  it('accepts a getter and a ref', async () => {
    mountWith(() => 'Programs');
    expect(document.title).toBe('Programs – My Workouts Online');

    const title = ref('Workouts');
    const wrapper = mountWith(title);
    expect(document.title).toBe('Workouts – My Workouts Online');

    title.value = 'History';
    await wrapper.vm.$nextTick();
    expect(document.title).toBe('History – My Workouts Online');
  });
});

describe('fullPageTitle', () => {
  it('puts the brand after the page title, or alone without one', () => {
    expect(fullPageTitle('Support')).toBe('Support – My Workouts Online');
    expect(fullPageTitle('')).toBe('My Workouts Online');
  });
});
