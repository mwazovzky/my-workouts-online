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
        assignments: JSON.parse(JSON.stringify(form.assignments)),
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

import ProgramForm from '@/Components/Admin/ProgramForm.vue';

describe('ProgramForm', () => {
  const templates = [
    { id: 1, name: 'Endurance Ride' },
    { id: 2, name: 'Recovery Ride' },
  ];

  beforeEach(() => {
    lastSubmit = undefined;
    shouldFail = false;
    vi.clearAllMocks();
  });

  function buildWrapper(props = {}) {
    return mount(ProgramForm, { props: { templates, ...props } });
  }

  it('posts to the create endpoint', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('post');
    expect(lastSubmit.url).toBe('/api/v1/admin/programs');
  });

  it('adds a weekday assignment defaulting to the first template and Monday', async () => {
    const wrapper = buildWrapper();

    await wrapper.get('button').trigger('click'); // "Add assignment"
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.assignments).toEqual([{ workout_template_id: 1, weekday: 'Monday' }]);
  });

  it('puts to the update endpoint and carries existing assignments', async () => {
    const wrapper = buildWrapper({
      program: {
        id: 5,
        translations: { name: { en: 'Beginner' }, description: {} },
        assignments: [{ workout_template_id: 2, weekday: 'Wednesday' }],
      },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('put');
    expect(lastSubmit.url).toBe('/api/v1/admin/programs/5');
    expect(lastSubmit.assignments).toEqual([{ workout_template_id: 2, weekday: 'Wednesday' }]);
  });

  it('disables adding assignments when there are no templates', () => {
    const wrapper = buildWrapper({ templates: [] });

    const addButton = wrapper.findAll('button').find(b => b.text() === 'Add assignment');
    expect(addButton.attributes('disabled')).toBeDefined();
  });

  it('binds all four translation fields to the submitted payload', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('#name_en').setValue('Beginner');
    await wrapper.find('#name_ru').setValue('Новичок');
    await wrapper.find('#description_en').setValue('Week 1');
    await wrapper.find('#description_ru').setValue('Неделя 1');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.translations).toEqual({
      en: { name: 'Beginner', description: 'Week 1' },
      ru: { name: 'Новичок', description: 'Неделя 1' },
    });
  });

  it('binds the weekday and template selects on an assignment row', async () => {
    const wrapper = buildWrapper();

    await wrapper.get('button').trigger('click'); // "Add assignment"
    await wrapper.find('#weekday_0').setValue('Thursday');
    // Option values are numeric ids, so select by option rather than by string value.
    await wrapper.find('#template_0').findAll('option')[1].setSelected();
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.assignments).toEqual([{ workout_template_id: 2, weekday: 'Thursday' }]);
  });

  it('removes an assignment when its remove button is clicked', async () => {
    const wrapper = buildWrapper({
      program: {
        id: 5,
        translations: { name: { en: 'Beginner' }, description: {} },
        assignments: [
          { workout_template_id: 1, weekday: 'Monday' },
          { workout_template_id: 2, weekday: 'Friday' },
        ],
      },
    });

    const removeButtons = wrapper.findAll('button').filter(b => b.text() === 'Remove');
    await removeButtons[0].trigger('click');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.assignments).toEqual([{ workout_template_id: 2, weekday: 'Friday' }]);
  });

  it('shows the empty-schedule hint when templates exist but nothing is scheduled', () => {
    const wrapper = buildWrapper();

    expect(wrapper.text()).toContain('No workouts scheduled yet');
    expect(wrapper.text()).not.toContain('Create a workout template first');
  });

  it('prompts to create a template when none exist', () => {
    const wrapper = buildWrapper({ templates: [] });

    expect(wrapper.text()).toContain('Create a workout template first');
  });

  it('starts with no assignments when the program has an empty schedule', async () => {
    const wrapper = buildWrapper({
      program: {
        id: 5,
        translations: { name: { en: 'Beginner' }, description: {} },
        assignments: [],
      },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.assignments).toEqual([]);
  });

  it('shows an error toast and does not emit saved when the request fails', async () => {
    shouldFail = true;
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(toast.error).toHaveBeenCalledWith('Failed to save program');
    expect(wrapper.emitted('saved')).toBeUndefined();
  });

  it('emits cancel when the cancel button is clicked', async () => {
    const wrapper = buildWrapper();

    const cancelButton = wrapper.findAll('button').find(b => b.text() === 'Cancel');
    await cancelButton.trigger('click');

    expect(wrapper.emitted('cancel')).toBeTruthy();
  });
});
