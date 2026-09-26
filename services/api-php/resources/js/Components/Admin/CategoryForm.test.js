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
      lastSubmit = { method, url, translations: JSON.parse(JSON.stringify(form.translations)) };
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

import CategoryForm from '@/Components/Admin/CategoryForm.vue';

describe('CategoryForm', () => {
  beforeEach(() => {
    lastSubmit = undefined;
    shouldFail = false;
    vi.clearAllMocks();
  });

  function buildWrapper(props = {}) {
    return mount(CategoryForm, { props });
  }

  it('posts to the create endpoint when no category is provided', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit).toMatchObject({ method: 'post', url: '/api/v1/admin/categories' });
  });

  it('puts to the update endpoint when editing', async () => {
    const wrapper = buildWrapper({
      category: { id: 7, translations: { name: { en: 'Chest', ru: 'Грудь' } } },
    });

    await wrapper.find('form').trigger('submit');

    expect(lastSubmit).toMatchObject({ method: 'put', url: '/api/v1/admin/categories/7' });
  });

  it('emits saved with the response data', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(wrapper.emitted('saved')).toBeTruthy();
    expect(wrapper.emitted('saved')[0]).toEqual([{ id: 1 }]);
  });

  it('shows an error toast and does not emit saved when the request fails', async () => {
    shouldFail = true;
    const wrapper = buildWrapper();

    await wrapper.find('form').trigger('submit');

    expect(toast.error).toHaveBeenCalledWith('Failed to save category');
    expect(wrapper.emitted('saved')).toBeUndefined();
  });

  it('binds both translation inputs to the submitted payload', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('#name_en').setValue('Back');
    await wrapper.find('#name_ru').setValue('Спина');
    await wrapper.find('form').trigger('submit');

    expect(lastSubmit.translations).toEqual({ en: { name: 'Back' }, ru: { name: 'Спина' } });
  });

  it('pre-fills both translations when editing', () => {
    const wrapper = buildWrapper({
      category: { id: 7, translations: { name: { en: 'Chest', ru: 'Грудь' } } },
    });

    expect(wrapper.find('#name_en').element.value).toBe('Chest');
    expect(wrapper.find('#name_ru').element.value).toBe('Грудь');
  });

  it('emits cancel when the cancel button is clicked', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('button[type="button"]').trigger('click');

    expect(wrapper.emitted('cancel')).toBeTruthy();
  });
});
