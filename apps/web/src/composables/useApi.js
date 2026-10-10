import { api } from '@/lib/api';

export function useApi() {
  const get = (url, params = {}) => api.get(url, { params });
  const post = (url, data = {}) => api.post(url, data);
  const patch = (url, data = {}) => api.patch(url, data);
  const put = (url, data = {}, config = {}) => api.put(url, data, config);
  const del = url => api.delete(url);

  return { get, post, patch, put, del };
}
