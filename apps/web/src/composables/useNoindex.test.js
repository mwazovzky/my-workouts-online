import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { useNoindex } from '@/composables/useNoindex';

const robotsMeta = () => document.head.querySelectorAll('meta[name="robots"]');

function mountPage() {
  return mount(
    defineComponent({
      setup() {
        useNoindex();
        return () => h('div');
      },
    })
  );
}

describe('useNoindex', () => {
  it('adds a noindex robots meta while mounted and removes it on unmount', () => {
    const wrapper = mountPage();

    expect(robotsMeta()).toHaveLength(1);
    expect(robotsMeta()[0].getAttribute('content')).toBe('noindex');

    wrapper.unmount();

    expect(robotsMeta()).toHaveLength(0);
  });

  it('removes only its own tag when pages overlap', () => {
    const leaving = mountPage();
    const entering = mountPage();

    leaving.unmount();

    expect(robotsMeta()).toHaveLength(1);

    entering.unmount();

    expect(robotsMeta()).toHaveLength(0);
  });
});
