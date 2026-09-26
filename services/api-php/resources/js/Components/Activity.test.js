import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

import Activity from '@/Components/Activity.vue';
import Set from '@/Components/Set.vue';

describe('Activity', () => {
  function buildWrapper(props = {}) {
    return mount(Activity, {
      props: {
        activity: {
          clientKey: 'activity-7',
          id: 42,
          exercise_id: 3,
          exercise_name: 'Bench Press',
          rest_time_seconds: 90,
          exercise_equipment_name: 'Barbell',
          exercise_category_names: ['Chest'],
          exercise_effort_type: 'repetitions',
          exercise_effort_label: 'Reps',
          exercise_difficulty_unit: 'kilograms',
          exercise_difficulty_label: 'kg',
          sets: [
            { id: 1, order: 1, effort_value: 10, difficulty_value: 60, is_completed: false },
            { id: 2, order: 2, effort_value: 8, difficulty_value: 60, is_completed: false },
          ],
        },
        editable: true,
        ...props,
      },
    });
  }

  function findByLabel(wrapper, label) {
    return wrapper.find(`button[aria-label="${label}"]`);
  }

  it('emits add-set with the client key, not the persisted id', async () => {
    const wrapper = buildWrapper();

    await findByLabel(wrapper, 'Add set').trigger('click');

    expect(wrapper.emitted('add-set').at(-1)[0]).toEqual({ activityKey: 'activity-7' });
  });

  it('emits remove-activity with the client key', async () => {
    const wrapper = buildWrapper();

    await findByLabel(wrapper, 'Delete activity').trigger('click');

    expect(wrapper.emitted('remove-activity').at(-1)[0]).toBe('activity-7');
  });

  it('emits remove-set with the client key alongside the set id and order', async () => {
    const wrapper = buildWrapper();

    await findByLabel(wrapper, 'Remove set :order').trigger('click');

    expect(wrapper.emitted('remove-set').at(-1)[0]).toMatchObject({
      activityKey: 'activity-7',
      id: 1,
      order: 1,
    });
  });

  it('identifies a newly added activity that has no id yet', async () => {
    const wrapper = buildWrapper({
      activity: {
        clientKey: 'activity-9',
        id: null,
        exercise_id: 5,
        exercise_name: 'Push-up',
        rest_time_seconds: null,
        exercise_equipment_name: 'Bodyweight',
        exercise_category_names: [],
        exercise_effort_type: 'repetitions',
        exercise_effort_label: 'Reps',
        exercise_difficulty_unit: 'none',
        exercise_difficulty_label: '',
        sets: [
          { id: null, order: 1, effort_value: 0, difficulty_value: null, is_completed: false },
        ],
      },
    });

    await findByLabel(wrapper, 'Add set').trigger('click');

    expect(wrapper.emitted('add-set').at(-1)[0]).toEqual({ activityKey: 'activity-9' });
  });

  it('emits both an activity update and the toggle when a set is completed', async () => {
    const wrapper = buildWrapper();

    await findByLabel(wrapper, 'Mark set :order as complete').trigger('click');

    // The parent state is updated first, so the toggle acts on fresh sets.
    const updated = wrapper.emitted('update-activity').at(-1)[0];
    expect(updated.clientKey).toBe('activity-7');
    expect(updated.sets[0]).toMatchObject({ id: 1, order: 1, is_completed: true });
    expect(updated.sets[1]).toMatchObject({ id: 2, is_completed: false });

    expect(wrapper.emitted('set-completion-toggled').at(-1)[0]).toMatchObject({
      activityKey: 'activity-7',
      id: 1,
      order: 1,
      is_completed: true,
    });
  });

  // Sets on a newly added activity have no id until the first save, so updates
  // have to be matched by order instead.
  it('matches an updated set by order when it has no id yet', async () => {
    const wrapper = buildWrapper({
      activity: {
        clientKey: 'activity-9',
        id: null,
        exercise_id: 5,
        exercise_name: 'Push-up',
        rest_time_seconds: null,
        exercise_equipment_name: 'Bodyweight',
        exercise_category_names: [],
        exercise_effort_type: 'repetitions',
        exercise_effort_label: 'Reps',
        exercise_difficulty_unit: 'none',
        exercise_difficulty_label: '',
        sets: [
          { id: null, order: 1, effort_value: 0, difficulty_value: null, is_completed: false },
          { id: null, order: 2, effort_value: 0, difficulty_value: null, is_completed: false },
        ],
      },
    });

    await wrapper.findAllComponents(Set)[1].vm.$emit('update', {
      id: null,
      order: 2,
      effort_value: 15,
      difficulty_value: null,
      is_completed: false,
    });

    const updated = wrapper.emitted('update-activity').at(-1)[0];
    expect(updated.sets[0].effort_value).toBe(0);
    expect(updated.sets[1].effort_value).toBe(15);
  });

  it('hides the edit controls when not editable', () => {
    const wrapper = buildWrapper({ editable: false });

    expect(findByLabel(wrapper, 'Add set').exists()).toBe(false);
    expect(findByLabel(wrapper, 'Delete activity').exists()).toBe(false);
  });
});
