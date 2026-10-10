import { describe, expect, it, vi, beforeEach } from 'vitest';
import { AxiosError } from 'axios';
import { api } from '@/lib/api';
import { useApiForm } from '@/composables/useApiForm';
import { pointerToPath, problemDetail, problemFieldErrors } from '@/lib/problem';

function reject(status, data) {
  const response = { status, data, headers: {}, config: {}, statusText: '' };
  return new AxiosError('Request failed', 'ERR_BAD_REQUEST', {}, {}, response);
}

function problem422(errors) {
  return {
    type: 'https://my-workouts.example/problems/validation_failed',
    title: 'Validation failed',
    status: 422,
    detail: 'The given data was invalid.',
    instance: '/api/v1/workouts/1#9c6b4f1e-0000-4000-8000-000000000001',
    code: 'validation_failed',
    errors,
  };
}

describe('problem helpers', () => {
  it('converts JSON Pointers to dotted paths, unescaping ~1 then ~0', () => {
    expect(pointerToPath('/email')).toBe('email');
    expect(pointerToPath('/activities/0/sets/1/effort_value')).toBe(
      'activities.0.sets.1.effort_value'
    );
    expect(pointerToPath('/translations/en~1us/name')).toBe('translations.en/us.name');
    expect(pointerToPath('/a~0b')).toBe('a~b');
    expect(pointerToPath('/a~01')).toBe('a~1');
    expect(pointerToPath('')).toBe('');
    expect(pointerToPath(undefined)).toBe('');
  });

  it('keeps the first detail per path', () => {
    const errors = problemFieldErrors(
      problem422([
        { pointer: '/email', code: 'required', detail: 'Email is required.' },
        { pointer: '/email', code: 'invalid_format', detail: 'Email is invalid.' },
        { pointer: '/activities/0/sets/1/effort_value', code: 'out_of_range', detail: 'Too big.' },
      ])
    );

    expect(errors).toEqual({
      email: 'Email is required.',
      'activities.0.sets.1.effort_value': 'Too big.',
    });
  });

  it('tolerates a problem without errors and reads the detail', () => {
    expect(problemFieldErrors({ code: 'validation_failed' })).toEqual({});
    expect(problemDetail({ response: { data: { detail: 'Nope.' } } })).toBe('Nope.');
    expect(problemDetail(new Error('offline'))).toBeUndefined();
  });
});

describe('useApiForm', () => {
  beforeEach(() => {
    api.defaults.adapter = vi.fn();
  });

  it('flattens a 422 Problem into dotted field errors and calls onError', async () => {
    api.defaults.adapter = vi.fn(async () => {
      throw reject(
        422,
        problem422([
          { pointer: '/email', code: 'invalid_format', detail: 'Email is invalid.' },
          {
            pointer: '/activities/0/sets/1/effort_value',
            code: 'out_of_range',
            detail: 'Too big.',
          },
        ])
      );
    });
    const onError = vi.fn();
    const form = useApiForm({ email: 'x' });

    await form.post('/auth/login', { onError });

    expect(form.errors).toEqual({
      email: 'Email is invalid.',
      'activities.0.sets.1.effort_value': 'Too big.',
    });
    expect(onError).toHaveBeenCalledWith(form.errors);
    expect(form.processing).toBe(false);
  });

  it('routes non-422 failures to onFail and leaves errors empty', async () => {
    api.defaults.adapter = vi.fn(async () => {
      throw reject(409, { code: 'in_use', detail: 'In use.' });
    });
    const onFail = vi.fn();
    const onError = vi.fn();
    const form = useApiForm({ name: 'x' });

    await form.delete('/admin/equipment/1', { onFail, onError });

    expect(form.errors).toEqual({});
    expect(onError).not.toHaveBeenCalled();
    expect(onFail).toHaveBeenCalledOnce();
  });

  it('clears errors on success and marks recentlySuccessful', async () => {
    api.defaults.adapter = vi.fn(async config => ({
      status: 200,
      data: { data: { ok: true } },
      headers: {},
      config,
      statusText: '',
    }));
    const onSuccess = vi.fn();
    const form = useApiForm({ name: 'x' });
    form.errors = { name: 'old' };

    await form.patch('/profile', { onSuccess });

    expect(form.errors).toEqual({});
    expect(form.recentlySuccessful).toBe(true);
    expect(onSuccess).toHaveBeenCalledOnce();
  });

  it('reset and clearErrors behave as before', () => {
    const form = useApiForm({ name: 'a', email: 'b' });
    form.name = 'changed';
    form.email = 'changed';
    form.errors = { name: 'bad', email: 'bad' };

    form.clearErrors('name');
    expect(form.errors).toEqual({ email: 'bad' });

    form.reset('name');
    expect(form.name).toBe('a');
    expect(form.email).toBe('changed');
    expect(form.errors).toEqual({});

    form.errors = { email: 'bad' };
    form.reset();
    expect(form.email).toBe('b');
    expect(form.errors).toEqual({});
  });
});
