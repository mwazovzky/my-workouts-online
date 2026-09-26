import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({
    t: value => value,
  }),
}));

import ProgramCard from '@/Components/ProgramCard.vue';

describe('ProgramCard', () => {
  const program = {
    id: 1,
    name: 'Strength Builder',
    description: 'A four-week beginner strength plan.',
    start_date: '2026-03-10',
    end_date: '2026-04-07',
  };

  function buildWrapper(props = {}) {
    return mount(ProgramCard, {
      props: {
        program,
        ...props,
      },
      global: {
        stubs: {
          ModelCard: {
            template: `
                            <div>
                                <div class="title">{{ title }}</div>
                                <div class="description">{{ description }}</div>
                                <slot name="metadata" />
                                <slot name="actions" />
                            </div>
                        `,
            props: ['title', 'description'],
          },
          Badge: {
            name: 'Badge',
            template: '<span class="badge"><slot /></span>',
          },
          Button: {
            name: 'Button',
            emits: ['click'],
            template: '<button type="button"><slot /></button>',
          },
        },
      },
    });
  }

  it('emits enroll when the enroll button is clicked', async () => {
    const wrapper = buildWrapper({ isEnrolled: false });
    const enrollButton = wrapper.getComponent({ name: 'Button' });

    await enrollButton.vm.$emit('click');

    expect(wrapper.emitted('enroll')).toHaveLength(1);
    expect(wrapper.text()).toContain('Enroll in Program');
  });

  it('shows the enrolled badge and hides the enroll button for enrolled users', () => {
    const wrapper = buildWrapper({ isEnrolled: true });

    expect(wrapper.text()).toContain('Enrolled');
    expect(wrapper.find('button').exists()).toBe(false);
  });
});
