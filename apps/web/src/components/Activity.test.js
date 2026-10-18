import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

import Activity from '@/components/Activity.vue';
import Set from '@/components/Set.vue';

describe('Activity', () => {
  function buildWrapper(props = {}) {
    return mount(Activity, {
      props: {
        activity: {
          clientKey: 'activity-7',
          id: 42,
          exercise_id: 3,
          exercise: {
            id: 3,
            name: 'Bench Press',
            equipment_name: 'Barbell',
            effort_type: 'repetitions',
            difficulty_unit: 'kilograms',
            category_names: ['Chest'],
            rest_time_seconds: 90,
          },
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
        exercise: {
          id: 5,
          name: 'Push-up',
          equipment_name: 'Bodyweight',
          effort_type: 'repetitions',
          difficulty_unit: 'none',
          category_names: [],
          rest_time_seconds: null,
        },
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
        exercise: {
          id: 5,
          name: 'Push-up',
          equipment_name: 'Bodyweight',
          effort_type: 'repetitions',
          difficulty_unit: 'none',
          category_names: [],
          rest_time_seconds: null,
        },
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

  describe('description', () => {
    const description = 'Lower the bar to your chest, then press it up.';

    function withExercise(exercise, props = {}) {
      return buildWrapper({
        activity: {
          clientKey: 'activity-7',
          id: 42,
          exercise_id: 3,
          exercise: { id: 3, name: 'Bench Press', ...exercise },
          sets: [],
        },
        ...props,
      });
    }

    it('is hidden until the info button is pressed, then toggles', async () => {
      const wrapper = withExercise({ description });
      const info = findByLabel(wrapper, 'How to do this exercise');

      expect(info.attributes('aria-expanded')).toBe('false');
      expect(wrapper.text()).not.toContain(description);

      await info.trigger('click');

      expect(info.attributes('aria-expanded')).toBe('true');
      expect(wrapper.find(`#${info.attributes('aria-controls')}`).text()).toBe(description);

      await info.trigger('click');

      expect(wrapper.text()).not.toContain(description);
    });

    it('is available on read-only cards', async () => {
      const wrapper = withExercise({ description }, { editable: false });

      await findByLabel(wrapper, 'How to do this exercise').trigger('click');

      expect(wrapper.text()).toContain(description);
    });

    it.each([null, undefined, '', '   '])('offers no info button for %j', value => {
      const wrapper = withExercise({ description: value });

      expect(findByLabel(wrapper, 'How to do this exercise').exists()).toBe(false);
    });
  });
});
