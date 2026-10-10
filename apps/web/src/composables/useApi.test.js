import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({
  mockAxios: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    put: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockAxios, configureApiAuth: vi.fn() }));

import { useApi } from '@/composables/useApi';

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('get() calls axios.get with url and params', () => {
    const { get } = useApi();
    get('/workouts', { page: 2 });
    expect(mockAxios.get).toHaveBeenCalledWith('/workouts', { params: { page: 2 } });
  });

  it('get() defaults params to empty object', () => {
    const { get } = useApi();
    get('/workouts');
    expect(mockAxios.get).toHaveBeenCalledWith('/workouts', { params: {} });
  });

  it('post() calls axios.post with url and data', () => {
    const { post } = useApi();
    post('/workouts', { foo: 'bar' });
    expect(mockAxios.post).toHaveBeenCalledWith('/workouts', { foo: 'bar' });
  });

  it('patch() calls axios.patch with url and data', () => {
    const { patch } = useApi();
    patch('/me', { name: 'Alice' });
    expect(mockAxios.patch).toHaveBeenCalledWith('/me', { name: 'Alice' });
  });

  it('del() calls axios.delete with url', () => {
    const { del } = useApi();
    del('/workouts/42');
    expect(mockAxios.delete).toHaveBeenCalledWith('/workouts/42');
  });

  it('put() calls axios.put with url, data and an empty config', () => {
    const { put } = useApi();
    put('/admin/equipment/1', { name: 'Bar' });
    expect(mockAxios.put).toHaveBeenCalledWith('/admin/equipment/1', { name: 'Bar' }, {});
  });

  it('put() passes request config such as headers through to axios', () => {
    const { put } = useApi();
    put('/workouts/1/activities', { activities: [] }, { headers: { 'If-Match': '"1-1"' } });
    expect(mockAxios.put).toHaveBeenCalledWith(
      '/workouts/1/activities',
      { activities: [] },
      { headers: { 'If-Match': '"1-1"' } }
    );
  });
});
