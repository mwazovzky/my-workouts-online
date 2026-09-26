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
      lastSubmit = { method, url, data: JSON.parse(JSON.stringify(form)) };
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

import EquipmentForm from '@/Components/Admin/EquipmentForm.vue';

describe('EquipmentForm', () => {
  beforeEach(() => {
    lastSubmit = undefined;
    shouldFail = false;
    vi.clearAllMocks();
  });

  function buildWrapper(props = {}) {
    return mount(EquipmentForm, { props });
  }

  it('posts to the create endpoint when no equipment is provided', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('post');
    expect(lastSubmit.url).toBe('/api/v1/admin/equipment');
  });

  it('defaults the difficulty unit to kilograms when creating', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data.difficulty_unit).toBe('kilograms');
  });

  it('offers every difficulty unit as an option', () => {
    const wrapper = buildWrapper();

    const options = wrapper.find('select').findAll('option');
    expect(options.map(o => o.attributes('value'))).toEqual([
      'kilograms',
      'pounds',
      'plates',
      'heart_rate_zone',
      'none',
    ]);
  });

  it('binds the unit select and both translation inputs to the submitted payload', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('select').setValue('plates');
    await wrapper.find('#name_en').setValue('Barbell');
    await wrapper.find('#name_ru').setValue('Штанга');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.data.difficulty_unit).toBe('plates');
    expect(lastSubmit.data.translations).toEqual({
      en: { name: 'Barbell' },
      ru: { name: 'Штанга' },
    });
  });

  it('puts to the update endpoint when editing', async () => {
    const wrapper = buildWrapper({
      equipment: {
        id: 4,
        difficulty_unit: 'heart_rate_zone',
        translations: { name: { en: 'Bike', ru: 'Велосипед' } },
      },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.method).toBe('put');
    expect(lastSubmit.url).toBe('/api/v1/admin/equipment/4');
  });

  it('pre-fills the unit and both translations when editing', () => {
    const wrapper = buildWrapper({
      equipment: {
        id: 4,
        difficulty_unit: 'pounds',
        translations: { name: { en: 'Bike', ru: 'Велосипед' } },
      },
    });

    expect(wrapper.find('select').element.value).toBe('pounds');
    expect(wrapper.find('#name_en').element.value).toBe('Bike');
    expect(wrapper.find('#name_ru').element.value).toBe('Велосипед');
  });

  it('emits saved with the response data', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(wrapper.emitted('saved')[0]).toEqual([{ id: 1 }]);
  });

  it('shows an error toast and does not emit saved when the request fails', async () => {
    shouldFail = true;
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(toast.error).toHaveBeenCalledWith('Failed to save equipment');
    expect(wrapper.emitted('saved')).toBeUndefined();
  });

  it('emits cancel when the cancel button is clicked', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('button[type="button"]').trigger('click');

    expect(wrapper.emitted('cancel')).toBeTruthy();
  });
});
