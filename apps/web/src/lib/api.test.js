import { describe, expect, it, vi, beforeEach } from 'vitest';
import { AxiosError } from 'axios';
import { api, configureApiAuth, isApiError } from '@/lib/api';

function respond(status, data = {}, config = {}) {
  const response = { status, data, headers: {}, config, statusText: '' };
  if (status >= 400) {
    throw new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, {}, response);
  }
  return response;
}

describe('api client', () => {
  let handlers;

  beforeEach(() => {
    handlers = {
      getAccessToken: vi.fn(() => 'token-1'),
      getLocale: vi.fn(() => 'ru'),
      refresh: vi.fn(async () => true),
      onAuthFailure: vi.fn(),
    };
    configureApiAuth(handlers);
  });

  it('attaches the bearer token and Accept-Language', async () => {
    api.defaults.adapter = vi.fn(async config => respond(200, { ok: true }, config));

    await api.get('/me');

    const config = api.defaults.adapter.mock.calls[0][0];
    expect(config.headers.Authorization).toBe('Bearer token-1');
    expect(config.headers['Accept-Language']).toBe('ru');
    expect(config.headers['X-Requested-With']).toBe('XMLHttpRequest');
  });

  it('refreshes once and retries when the access token expired', async () => {
    let calls = 0;
    api.defaults.adapter = vi.fn(async config => {
      calls += 1;
      if (calls === 1) {
        return respond(401, { code: 'token_expired' }, config);
      }
      return respond(200, { data: 'fresh' }, config);
    });

    const { data } = await api.get('/me');

    expect(data).toEqual({ data: 'fresh' });
    expect(handlers.refresh).toHaveBeenCalledTimes(1);
    expect(calls).toBe(2);
  });

  it('gives up after one retry and reports auth failure when refresh fails', async () => {
    handlers.refresh.mockResolvedValue(false);
    api.defaults.adapter = vi.fn(async config => respond(401, { code: 'token_expired' }, config));

    await expect(api.get('/me')).rejects.toBeInstanceOf(AxiosError);

    expect(handlers.refresh).toHaveBeenCalledTimes(1);
    expect(handlers.onAuthFailure).toHaveBeenCalledTimes(1);
    expect(api.defaults.adapter).toHaveBeenCalledTimes(1);
  });

  it('coalesces concurrent refreshes', async () => {
    let resolveRefresh;
    handlers.refresh.mockImplementation(() => new Promise(resolve => (resolveRefresh = resolve)));
    api.defaults.adapter = vi.fn(async config =>
      config._retried ? respond(200, {}, config) : respond(401, { code: 'token_expired' }, config)
    );

    const pending = Promise.all([api.get('/a'), api.get('/b')]);
    await vi.waitFor(() => expect(handlers.refresh).toHaveBeenCalledTimes(1));
    resolveRefresh(true);
    await pending;

    expect(handlers.refresh).toHaveBeenCalledTimes(1);
  });

  it('does not refresh for other 401s or for the refresh call itself', async () => {
    api.defaults.adapter = vi.fn(async config => respond(401, { code: 'unauthenticated' }, config));
    await expect(api.get('/me')).rejects.toBeInstanceOf(AxiosError);

    api.defaults.adapter = vi.fn(async config => respond(401, { code: 'token_expired' }, config));
    await expect(api.post('/auth/refresh')).rejects.toBeInstanceOf(AxiosError);

    expect(handlers.refresh).not.toHaveBeenCalled();
  });

  it('isApiError inspects the envelope code', () => {
    const error = new AxiosError('x', 'y', {}, {}, { data: { code: 'not_found' } });

    expect(isApiError(error)).toBe(true);
    expect(isApiError(error, 'not_found')).toBe(true);
    expect(isApiError(error, 'forbidden')).toBe(false);
    expect(isApiError(new Error('plain'))).toBe(false);
  });
});

describe('api client defaults', () => {
  it('sends no bearer, English, and gives up on expiry until configured', async () => {
    vi.resetModules();
    const fresh = await import('@/lib/api');
    let calls = 0;
    fresh.api.defaults.adapter = vi.fn(async config => {
      calls += 1;
      return respond(401, { code: 'token_expired' }, config);
    });

    await expect(fresh.api.get('/me')).rejects.toBeInstanceOf(AxiosError);

    const config = fresh.api.defaults.adapter.mock.calls[0][0];
    expect(config.headers.Authorization).toBeUndefined();
    expect(config.headers['Accept-Language']).toBe('en');
    expect(calls).toBe(1);
  });
});
