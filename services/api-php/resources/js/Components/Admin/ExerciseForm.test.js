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
      lastSubmit = { method, url, data: { ...form, category_ids: [...form.category_ids] } };
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

import ExerciseForm from '@/Components/Admin/ExerciseForm.vue';

describe('ExerciseForm', () => {
  const equipmentOptions = [
    { id: 1, name: 'Barbell' },
    { id: 2, name: 'Dumbbell' },
  ];
  const categoryOptions = [
    { id: 10, name: 'Chest' },
    { id: 20, name: 'Legs' },
  ];

  beforeEach(() => {
    lastSubmit = undefined;
    shouldFail = false;
    vi.clearAllMocks();
  });

  function buildWrapper(props = {}) {
    return mount(ExerciseForm, {
      props: { equipmentOptions, categoryOptions, ...props },
    });
  }

  it('posts to the create endpoint with default equipment when creating', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('post');
    expect(lastSubmit.url).toBe('/api/v1/admin/exercises');
    expect(lastSubmit.data.equipment_id).toBe(1);
  });

  it('puts to the update endpoint when editing', async () => {
    const wrapper = buildWrapper({
      exercise: {
        id: 5,
        equipment_id: 2,
        effort_type: 'duration',
        rest_time_seconds: 60,
        category_ids: [20],
        translations: { name: { en: 'Plank' }, description: { en: '' } },
      },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('put');
    expect(lastSubmit.url).toBe('/api/v1/admin/exercises/5');
    expect(lastSubmit.data.category_ids).toEqual([20]);
  });

  it('pre-checks categories that belong to the exercise', () => {
    const wrapper = buildWrapper({
      exercise: {
        id: 5,
        equipment_id: 1,
        effort_type: 'repetitions',
        rest_time_seconds: 90,
        category_ids: [10],
        translations: { name: { en: 'Bench' }, description: { en: '' } },
      },
    });

    const checkboxes = wrapper.findAll('input[type="checkbox"]');
    expect(checkboxes[0].element.checked).toBe(true);
    expect(checkboxes[1].element.checked).toBe(false);
  });

  it('toggles a category when its checkbox is clicked', async () => {
    const wrapper = buildWrapper();

    const checkboxes = wrapper.findAll('input[type="checkbox"]');
    await checkboxes[0].setValue(true);

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data.category_ids).toContain(10);
  });

  it('binds every scalar field to the submitted payload', async () => {
    const wrapper = buildWrapper();

    const selects = wrapper.findAll('select');
    // Equipment option values are numeric ids, so select by option rather than by string value.
    await selects[0].findAll('option')[1].setSelected();
    await selects[1].setValue('duration'); // effort type
    await wrapper.find('#rest_time_seconds').setValue('45');
    await wrapper.find('#name_en').setValue('Plank');
    await wrapper.find('#name_ru').setValue('Планка');
    await wrapper.find('#description_en').setValue('Hold it');
    await wrapper.find('#description_ru').setValue('Держите');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data).toMatchObject({
      equipment_id: 2,
      effort_type: 'duration',
      rest_time_seconds: 45,
    });
    expect(lastSubmit.data.translations).toEqual({
      en: { name: 'Plank', description: 'Hold it' },
      ru: { name: 'Планка', description: 'Держите' },
    });
  });

  it('unchecks a category that was already selected', async () => {
    const wrapper = buildWrapper({
      exercise: {
        id: 5,
        equipment_id: 1,
        effort_type: 'repetitions',
        rest_time_seconds: null,
        category_ids: [10, 20],
        translations: { name: { en: 'Bench' }, description: { en: '' } },
      },
    });

    const checkboxes = wrapper.findAll('input[type="checkbox"]');
    await checkboxes[0].setValue(false);

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data.category_ids).toEqual([20]);
  });

  it('falls back to an empty equipment id when there are no equipment options', async () => {
    const wrapper = mount(ExerciseForm, {
      props: { equipmentOptions: [], categoryOptions },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data.equipment_id).toBe('');
  });

  it('shows an error toast and does not emit saved when the request fails', async () => {
    shouldFail = true;
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(toast.error).toHaveBeenCalledWith('Failed to save exercise');
    expect(wrapper.emitted('saved')).toBeUndefined();
  });

  it('emits cancel when the cancel button is clicked', async () => {
    const wrapper = buildWrapper();

    const cancelButton = wrapper.findAll('button').find(b => b.text() === 'Cancel');
    await cancelButton.trigger('click');

    expect(wrapper.emitted('cancel')).toBeTruthy();
  });
});
