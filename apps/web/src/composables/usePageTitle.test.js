import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { usePageTitle } from '@/composables/usePageTitle';

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
  it('sets the document title from a string with the app suffix', () => {
    mountWith('Dashboard');

    expect(document.title).toBe('Dashboard - My Workouts');
  });

  it('accepts a getter and a ref', async () => {
    mountWith(() => 'Programs');
    expect(document.title).toBe('Programs - My Workouts');

    const title = ref('Workouts');
    const wrapper = mountWith(title);
    expect(document.title).toBe('Workouts - My Workouts');

    title.value = 'History';
    await wrapper.vm.$nextTick();
    expect(document.title).toBe('History - My Workouts');
  });
});
