import axios from 'axios';

export function useApi() {
  const get = (url, params = {}) => axios.get(url, { params });
  const post = (url, data = {}) => axios.post(url, data);
  const patch = (url, data = {}) => axios.patch(url, data);
  const del = url => axios.delete(url);

  return { get, post, patch, del };
}
