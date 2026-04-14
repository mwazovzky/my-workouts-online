import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({
  mockAxios: {
    patch: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('axios', () => ({ default: mockAxios }));

import { useApiForm } from '@/composables/useApiForm';

describe('useApiForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  // -------------------------------------------------------
  // Initialisation
  // -------------------------------------------------------

  it('initialises with provided data and correct meta fields', () => {
    const form = useApiForm({ name: 'Alice', age: 30 });

    expect(form.name).toBe('Alice');
    expect(form.age).toBe(30);
    expect(form.errors).toEqual({});
    expect(form.processing).toBe(false);
    expect(form.recentlySuccessful).toBe(false);
  });

  // -------------------------------------------------------
  // reset()
  // -------------------------------------------------------

  it('reset() with no args restores all fields and clears errors', () => {
    const form = useApiForm({ name: 'Alice' });
    form.name = 'Bob';
    form.errors = { name: 'required' };

    form.reset();

    expect(form.name).toBe('Alice');
    expect(form.errors).toEqual({});
  });

  it('reset(field) restores only the specified field and clears errors', () => {
    const form = useApiForm({ name: 'Alice', email: 'a@a.com' });
    form.name = 'Bob';
    form.email = 'b@b.com';
    form.errors = { name: 'required' };

    form.reset('name');

    expect(form.name).toBe('Alice');
    expect(form.email).toBe('b@b.com');
    expect(form.errors).toEqual({});
  });

  // -------------------------------------------------------
  // clearErrors()
  // -------------------------------------------------------

  it('clearErrors() removes all errors', () => {
    const form = useApiForm({ name: '' });
    form.errors = { name: 'required', email: 'invalid' };

    form.clearErrors();

    expect(form.errors).toEqual({});
  });

  it('clearErrors(field) removes only the specified error', () => {
    const form = useApiForm({ name: '' });
    form.errors = { name: 'required', email: 'invalid' };

    form.clearErrors('name');

    expect(form.errors).toEqual({ email: 'invalid' });
  });

  // -------------------------------------------------------
  // HTTP methods — payload excludes meta keys
  // -------------------------------------------------------

  it('patch() calls axios.patch with form data, excluding meta keys', async () => {
    mockAxios.patch.mockResolvedValueOnce({ data: {} });
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile');

    expect(mockAxios.patch).toHaveBeenCalledWith('/api/v1/profile', { name: 'Alice' });
  });

  it('put() calls axios.put with form data', async () => {
    mockAxios.put.mockResolvedValueOnce({ data: {} });
    const form = useApiForm({ password: 'secret' });

    await form.put('/api/v1/profile/password');

    expect(mockAxios.put).toHaveBeenCalledWith('/api/v1/profile/password', { password: 'secret' });
  });

  it('delete() calls axios.delete with form data as body', async () => {
    mockAxios.delete.mockResolvedValueOnce({ data: {} });
    const form = useApiForm({ password: 'secret' });

    await form.delete('/api/v1/profile');

    expect(mockAxios.delete).toHaveBeenCalledWith('/api/v1/profile', {
      data: { password: 'secret' },
    });
  });

  // -------------------------------------------------------
  // Success path
  // -------------------------------------------------------

  it('sets processing=true during the call and false after', async () => {
    let processingDuringCall;
    mockAxios.patch.mockImplementationOnce(async () => {
      processingDuringCall = true;
      return { data: {} };
    });
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile');

    expect(processingDuringCall).toBe(true);
    expect(form.processing).toBe(false);
  });

  it('sets recentlySuccessful=true on success, then resets to false after 2000ms', async () => {
    mockAxios.patch.mockResolvedValueOnce({ data: {} });
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile');

    expect(form.recentlySuccessful).toBe(true);

    vi.runAllTimers();

    expect(form.recentlySuccessful).toBe(false);
  });

  it('calls onSuccess with the response on success', async () => {
    const response = { data: { id: 1 } };
    mockAxios.patch.mockResolvedValueOnce(response);
    const onSuccess = vi.fn();
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile', { onSuccess });

    expect(onSuccess).toHaveBeenCalledWith(response);
  });

  it('calls onFinish on success', async () => {
    mockAxios.patch.mockResolvedValueOnce({ data: {} });
    const onFinish = vi.fn();
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile', { onFinish });

    expect(onFinish).toHaveBeenCalledOnce();
  });

  // -------------------------------------------------------
  // 422 error path
  // -------------------------------------------------------

  it('flattens 422 errors into form.errors and calls onError', async () => {
    mockAxios.patch.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          errors: { name: ['Name is required.', 'Name is too short.'], email: ['Invalid email.'] },
        },
      },
    });
    const onError = vi.fn();
    const form = useApiForm({ name: '', email: '' });

    await form.patch('/api/v1/profile', { onError });

    expect(form.errors).toEqual({ name: 'Name is required.', email: 'Invalid email.' });
    expect(onError).toHaveBeenCalledWith({ name: 'Name is required.', email: 'Invalid email.' });
  });

  it('calls onFinish on 422 error', async () => {
    mockAxios.patch.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { name: ['required'] } } },
    });
    const onFinish = vi.fn();
    const form = useApiForm({ name: '' });

    await form.patch('/api/v1/profile', { onFinish });

    expect(onFinish).toHaveBeenCalledOnce();
  });

  // -------------------------------------------------------
  // Non-422 error path
  // -------------------------------------------------------

  it('does not call onError on non-422 errors', async () => {
    mockAxios.patch.mockRejectedValueOnce({ response: { status: 500 } });
    const onError = vi.fn();
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile', { onError });

    expect(form.errors).toEqual({});
    expect(onError).not.toHaveBeenCalled();
    expect(form.processing).toBe(false);
  });

  it('calls onFinish on non-422 error', async () => {
    mockAxios.patch.mockRejectedValueOnce({ response: { status: 500 } });
    const onFinish = vi.fn();
    const form = useApiForm({ name: 'Alice' });

    await form.patch('/api/v1/profile', { onFinish });

    expect(onFinish).toHaveBeenCalledOnce();
  });
});
