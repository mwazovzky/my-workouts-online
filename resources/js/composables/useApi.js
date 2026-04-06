export function useApi() {
  const get = (url, params = {}) => window.axios.get(url, { params });
  const post = (url, data = {}) => window.axios.post(url, data);
  const patch = (url, data = {}) => window.axios.patch(url, data);
  const del = (url) => window.axios.delete(url);

  return { get, post, patch, del };
}
