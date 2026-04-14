import axios from 'axios';
import { reactive } from 'vue';

const META_KEYS = new Set([
  'errors',
  'processing',
  'recentlySuccessful',
  'reset',
  'clearErrors',
  'patch',
  'put',
  'delete',
]);

export function useApiForm(initialData) {
  const defaults = { ...initialData };

  const form = reactive({
    ...initialData,
    errors: {},
    processing: false,
    recentlySuccessful: false,
  });

  let recentlySuccessfulTimer = null;

  form.reset = (...fields) => {
    if (fields.length === 0) {
      Object.assign(form, { ...defaults });
    } else {
      fields.forEach(field => {
        form[field] = defaults[field];
      });
    }
    form.errors = {};
  };

  form.clearErrors = (...fields) => {
    if (fields.length === 0) {
      form.errors = {};
    } else {
      fields.forEach(field => {
        delete form.errors[field];
      });
    }
  };

  const getFormData = () => {
    const data = {};
    for (const key in form) {
      if (!META_KEYS.has(key)) {
        data[key] = form[key];
      }
    }
    return data;
  };

  const submit = async (method, url, options = {}) => {
    form.processing = true;
    form.errors = {};

    try {
      let response;
      if (method === 'delete') {
        response = await axios.delete(url, { data: getFormData() });
      } else {
        response = await axios[method](url, getFormData());
      }

      form.recentlySuccessful = true;
      window.clearTimeout(recentlySuccessfulTimer);
      recentlySuccessfulTimer = window.setTimeout(() => {
        form.recentlySuccessful = false;
      }, 2000);

      options.onSuccess?.(response);
    } catch (error) {
      if (error.response?.status === 422) {
        const rawErrors = error.response.data.errors ?? {};
        const flattened = {};
        for (const key in rawErrors) {
          flattened[key] = Array.isArray(rawErrors[key]) ? rawErrors[key][0] : rawErrors[key];
        }
        form.errors = flattened;
        options.onError?.(form.errors);
      }
    } finally {
      form.processing = false;
      options.onFinish?.();
    }
  };

  form.patch = (url, options = {}) => submit('patch', url, options);
  form.put = (url, options = {}) => submit('put', url, options);
  form.delete = (url, options = {}) => submit('delete', url, options);

  return form;
}
