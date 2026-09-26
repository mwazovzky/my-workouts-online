import axios from 'axios';

/**
 * Single axios instance for the API. Auth wiring is injected by the auth
 * store (see `configureApiAuth`) to avoid a circular import.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

const handlers = {
  getAccessToken: () => null,
  getLocale: () => 'en',
  refresh: async () => false,
  onAuthFailure: () => {},
};

export function configureApiAuth(overrides) {
  Object.assign(handlers, overrides);
}

let refreshInFlight = null;

/**
 * Coalesces concurrent refreshes so a burst of expired requests triggers one
 * refresh call.
 */
function refreshOnce() {
  if (!refreshInFlight) {
    refreshInFlight = Promise.resolve()
      .then(() => handlers.refresh())
      .finally(() => {
        refreshInFlight = null;
      });
  }

  return refreshInFlight;
}

api.interceptors.request.use(config => {
  const token = handlers.getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  config.headers['Accept-Language'] = handlers.getLocale();
  return config;
});

api.interceptors.response.use(
  response => response,
  async error => {
    const { config, response } = error;
    const isRefreshCall = config?.url?.includes('/auth/refresh');

    if (
      response?.status === 401 &&
      response.data?.code === 'token_expired' &&
      config &&
      !config._retried &&
      !isRefreshCall
    ) {
      config._retried = true;
      const refreshed = await refreshOnce();
      if (refreshed) {
        return api(config);
      }
      handlers.onAuthFailure();
    }

    return Promise.reject(error);
  }
);

export function isApiError(error, code) {
  return (
    Boolean(error?.response?.data?.code) &&
    (code === undefined || error.response.data.code === code)
  );
}
