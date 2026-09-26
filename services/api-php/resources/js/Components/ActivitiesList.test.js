import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

import ActivitiesList from '@/Components/ActivitiesList.vue';
import Activity from '@/Components/Activity.vue';

function buildActivity(overrides = {}) {
  return {
    clientKey: 'activity-1',
    id: 10,
    exercise_id: 3,
    exercise_name: 'Bench Press',
    rest_time_seconds: 90,
    exercise_equipment_name: 'Barbell',
    exercise_category_names: ['Chest'],
    exercise_effort_type: 'repetitions',
    exercise_effort_label: 'Reps',
    exercise_difficulty_unit: 'kilograms',
    exercise_difficulty_label: 'kg',
    sets: [{ id: 100, order: 1, effort_value: 10, difficulty_value: 60, is_completed: false }],
    ...overrides,
  };
}

describe('ActivitiesList', () => {
  function buildWrapper(props = {}) {
    return mount(ActivitiesList, {
      props: {
        activities: [
          buildActivity(),
          buildActivity({ clientKey: 'activity-2', id: 11, exercise_name: 'Deadlift' }),
        ],
        editable: true,
        ...props,
      },
    });
  }

  it('renders one card per activity', () => {
    const wrapper = buildWrapper();

    expect(wrapper.findAllComponents(Activity)).toHaveLength(2);
    expect(wrapper.findAll('h3').map(title => title.text())).toEqual(['Bench Press', 'Deadlift']);
  });

  // WorkoutShow passes the raw API payload straight through, and those
  // activities have no clientKey — the list must fall back to the id.
  it('keys read-only activities that have no client key', () => {
    const wrapper = buildWrapper({
      activities: [
        buildActivity({ clientKey: undefined }),
        buildActivity({ clientKey: undefined, id: 11, exercise_name: 'Deadlift' }),
      ],
      editable: false,
    });

    const cards = wrapper.findAllComponents(Activity);
    expect(cards).toHaveLength(2);
    expect(cards.map(card => card.props('activity').id)).toEqual([10, 11]);
    expect(wrapper.findAll('h3').map(title => title.text())).toEqual(['Bench Press', 'Deadlift']);
  });

  it('emits reorder when the draggable list changes', async () => {
    const wrapper = buildWrapper();

    await wrapper.findComponent({ name: 'VueDraggableNext' }).vm.$emit('change');

    expect(wrapper.emitted('reorder')).toHaveLength(1);
  });

  it.each([
    ['update-activity', { clientKey: 'activity-1', sets: [] }],
    ['add-set', { activityKey: 'activity-1' }],
    ['remove-set', { activityKey: 'activity-1', id: 100, order: 1 }],
    ['remove-activity', 'activity-1'],
    ['set-completion-toggled', { activityKey: 'activity-1', id: 100, is_completed: true }],
  ])('forwards %s from the child activity', async (event, payload) => {
    const wrapper = buildWrapper();

    await wrapper.findAllComponents(Activity)[0].vm.$emit(event, payload);

    expect(wrapper.emitted(event)).toHaveLength(1);
    expect(wrapper.emitted(event)[0][0]).toEqual(payload);
  });
});
