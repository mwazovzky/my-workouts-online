import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { toast } from 'vue-sonner';

let lastSubmit;
let shouldFail;

vi.mock('@/composables/useApiForm', () => ({
  useApiForm: initial => {
    const form = reactive({ ...initial, errors: {}, processing: false });
    const respond = (method, url, opts) => {
      lastSubmit = {
        method,
        url,
        data: JSON.parse(JSON.stringify(form.activities)),
        translations: JSON.parse(JSON.stringify(form.translations)),
      };
      if (shouldFail) {
        opts.onFail?.();
      } else {
        opts.onSuccess?.({ data: { data: { id: 1 } } });
      }
    };
    form.post = (url, opts) => respond('post', url, opts);
    form.put = (url, opts) => respond('put', url, opts);
    return form;
  },
}));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

vi.mock('vue-sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

import WorkoutTemplateForm from '@/Components/Admin/WorkoutTemplateForm.vue';

describe('WorkoutTemplateForm', () => {
  const exercises = [
    {
      id: 1,
      name: 'Bench Press',
      effort_label: 'Reps',
      difficulty_unit: 'kilograms',
      difficulty_label: 'Weight (kg)',
    },
    {
      id: 2,
      name: 'Endurance Ride',
      effort_label: 'Duration',
      difficulty_unit: 'heart_rate_zone',
      difficulty_label: 'Zone',
    },
    {
      id: 3,
      name: 'Plank',
      effort_label: 'Duration',
      difficulty_unit: 'none',
      difficulty_label: '',
    },
  ];

  beforeEach(() => {
    lastSubmit = undefined;
    shouldFail = false;
    vi.clearAllMocks();
  });

  function buildWrapper(props = {}) {
    return mount(WorkoutTemplateForm, { props: { exercises, ...props } });
  }

  function buttonsLabelled(wrapper, label) {
    return wrapper.findAll('button').filter(b => b.text() === label);
  }

  function templateWith(activities) {
    return {
      template: {
        id: 9,
        translations: { name: { en: 'Session' }, description: {} },
        activities,
      },
    };
  }

  it('adds an activity with a default set', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('button[type="button"]').trigger('click'); // "Add activity"

    expect(wrapper.findAll('select')).not.toHaveLength(0);
    // One activity, one default set row with an effort input
    expect(wrapper.find('input[type="number"]').exists()).toBe(true);
  });

  it('posts to the create endpoint with normalized sequential orders', async () => {
    const wrapper = buildWrapper();

    // add one activity (defaults to first exercise = weight unit)
    await wrapper.get('button').trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('post');
    expect(lastSubmit.url).toBe('/api/v1/admin/workout-templates');
    expect(lastSubmit.data[0].order).toBe(1);
    expect(lastSubmit.data[0].sets[0].order).toBe(1);
  });

  it('puts to the update endpoint when editing an existing template', async () => {
    const wrapper = buildWrapper({
      template: {
        id: 9,
        translations: { name: { en: 'Full Body' }, description: {} },
        activities: [{ exercise_id: 1, sets: [{ effort_value: 10, difficulty_value: 50 }] }],
      },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('put');
    expect(lastSubmit.url).toBe('/api/v1/admin/workout-templates/9');
  });

  it('renders a zone select when the activity uses a heart-rate-zone exercise', async () => {
    const wrapper = buildWrapper({
      template: {
        id: 9,
        translations: { name: { en: 'Ride' }, description: {} },
        activities: [{ exercise_id: 2, sets: [{ effort_value: 1800, difficulty_value: 2 }] }],
      },
    });

    // Two selects: the exercise picker + the zone picker for the set
    const selects = wrapper.findAll('select');
    expect(selects.length).toBe(2);
    // The zone picker has the "—" option plus 1..5
    expect(selects[1].findAll('option')).toHaveLength(6);
  });

  it('renders no difficulty input for a bodyweight (none) exercise', () => {
    const wrapper = buildWrapper({
      template: {
        id: 9,
        translations: { name: { en: 'Core' }, description: {} },
        activities: [{ exercise_id: 3, sets: [{ effort_value: 60, difficulty_value: null }] }],
      },
    });

    // Only the exercise picker select; no zone select
    expect(wrapper.findAll('select')).toHaveLength(1);
    expect(wrapper.find('input[step="0.5"]').exists()).toBe(false);
  });

  it('shows the empty state until an activity is added', async () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('Add at least one activity');

    await buttonsLabelled(wrapper, 'Add activity')[0].trigger('click');

    expect(wrapper.text()).not.toContain('Add at least one activity');
  });

  it('binds all four translation fields to the submitted payload', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('#name_en').setValue('Full Body');
    await wrapper.find('#name_ru').setValue('Всё тело');
    await wrapper.find('#description_en').setValue('Session A');
    await wrapper.find('#description_ru').setValue('Сессия A');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.translations).toEqual({
      en: { name: 'Full Body', description: 'Session A' },
      ru: { name: 'Всё тело', description: 'Сессия A' },
    });
  });

  it('binds the effort and weight inputs on a set row', async () => {
    const wrapper = buildWrapper(
      templateWith([{ exercise_id: 1, sets: [{ effort_value: 10, difficulty_value: 50 }] }])
    );

    const numberInputs = wrapper.findAll('input[type="number"]');
    await numberInputs[0].setValue('12');
    await numberInputs[1].setValue('62.5');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].sets[0]).toMatchObject({
      effort_value: 12,
      difficulty_value: 62.5,
    });
  });

  it('binds the zone select on a set row', async () => {
    const wrapper = buildWrapper(
      templateWith([{ exercise_id: 2, sets: [{ effort_value: 1800, difficulty_value: null }] }])
    );

    // Options are [null, 1, 2, 3, 4, 5]; index 4 is zone 4.
    await wrapper.findAll('select')[1].findAll('option')[4].setSelected();
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].sets[0].difficulty_value).toBe(4);
  });

  it('removes an activity when its remove button is clicked', async () => {
    const wrapper = buildWrapper(
      templateWith([
        { exercise_id: 1, sets: [{ effort_value: 10, difficulty_value: 50 }] },
        { exercise_id: 3, sets: [{ effort_value: 60, difficulty_value: null }] },
      ])
    );

    await buttonsLabelled(wrapper, 'Remove')[0].trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data).toHaveLength(1);
    expect(lastSubmit.data[0].exercise_id).toBe(3);
  });

  it('adds a set that inherits the exercise difficulty unit default', async () => {
    const wrapper = buildWrapper(
      templateWith([{ exercise_id: 1, sets: [{ effort_value: 10, difficulty_value: 50 }] }])
    );

    await buttonsLabelled(wrapper, 'Add set')[0].trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].sets).toHaveLength(2);
    // Weight units default to 0, not null.
    expect(lastSubmit.data[0].sets[1]).toMatchObject({ effort_value: 0, difficulty_value: 0 });
  });

  it('defaults a new set to a null difficulty for zone exercises', async () => {
    const wrapper = buildWrapper(
      templateWith([{ exercise_id: 2, sets: [{ effort_value: 1800, difficulty_value: 3 }] }])
    );

    await buttonsLabelled(wrapper, 'Add set')[0].trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].sets[1]).toMatchObject({ effort_value: 0, difficulty_value: null });
  });

  it('removes a single set without touching the others', async () => {
    const wrapper = buildWrapper(
      templateWith([
        {
          exercise_id: 1,
          sets: [
            { effort_value: 10, difficulty_value: 50 },
            { effort_value: 8, difficulty_value: 60 },
          ],
        },
      ])
    );

    await buttonsLabelled(wrapper, '−')[0].trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].sets).toHaveLength(1);
    expect(lastSubmit.data[0].sets[0]).toMatchObject({ effort_value: 8, difficulty_value: 60 });
  });

  it('resets set difficulties when the activity switches to another unit', async () => {
    const wrapper = buildWrapper(
      templateWith([
        {
          exercise_id: 1,
          sets: [
            { effort_value: 10, difficulty_value: 50 },
            { effort_value: 8, difficulty_value: 60 },
          ],
        },
      ])
    );

    // Switch from a kilograms exercise to a heart-rate-zone one.
    await wrapper.find('select').setValue(2);
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].sets.map(set => set.difficulty_value)).toEqual([null, null]);
  });

  it('numbers activities and sets sequentially across the whole form', async () => {
    const wrapper = buildWrapper(
      templateWith([
        {
          exercise_id: 1,
          sets: [
            { effort_value: 10, difficulty_value: 50 },
            { effort_value: 8, difficulty_value: 60 },
          ],
        },
        { exercise_id: 3, sets: [{ effort_value: 60, difficulty_value: null }] },
      ])
    );

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data.map(a => a.order)).toEqual([1, 2]);
    expect(lastSubmit.data[0].sets.map(s => s.order)).toEqual([1, 2]);
    expect(lastSubmit.data[1].sets.map(s => s.order)).toEqual([1]);
  });

  it('falls back to an empty exercise id when there are no exercises', async () => {
    const wrapper = mount(WorkoutTemplateForm, { props: { exercises: [] } });

    await buttonsLabelled(wrapper, 'Add activity')[0].trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data[0].exercise_id).toBe('');
  });

  it('shows an error toast and does not emit saved when the request fails', async () => {
    shouldFail = true;
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(toast.error).toHaveBeenCalledWith('Failed to save workout template');
    expect(wrapper.emitted('saved')).toBeUndefined();
  });

  it('emits cancel when the cancel button is clicked', async () => {
    const wrapper = buildWrapper();

    await buttonsLabelled(wrapper, 'Cancel')[0].trigger('click');

    expect(wrapper.emitted('cancel')).toBeTruthy();
  });
});
