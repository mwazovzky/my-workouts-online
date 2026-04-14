import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({
  mockAxios: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('axios', () => ({ default: mockAxios }));

import { useApi } from '@/composables/useApi';

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('get() calls axios.get with url and params', () => {
    const { get } = useApi();
    get('/api/v1/workouts', { page: 2 });
    expect(mockAxios.get).toHaveBeenCalledWith('/api/v1/workouts', { params: { page: 2 } });
  });

  it('get() defaults params to empty object', () => {
    const { get } = useApi();
    get('/api/v1/workouts');
    expect(mockAxios.get).toHaveBeenCalledWith('/api/v1/workouts', { params: {} });
  });

  it('post() calls axios.post with url and data', () => {
    const { post } = useApi();
    post('/api/v1/programs/1/enroll', { foo: 'bar' });
    expect(mockAxios.post).toHaveBeenCalledWith('/api/v1/programs/1/enroll', { foo: 'bar' });
  });

  it('patch() calls axios.patch with url and data', () => {
    const { patch } = useApi();
    patch('/api/v1/profile', { name: 'Alice' });
    expect(mockAxios.patch).toHaveBeenCalledWith('/api/v1/profile', { name: 'Alice' });
  });

  it('del() calls axios.delete with url', () => {
    const { del } = useApi();
    del('/api/v1/workouts/42');
    expect(mockAxios.delete).toHaveBeenCalledWith('/api/v1/workouts/42');
  });
});
