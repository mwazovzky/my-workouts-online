import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({
  mockAxios: { put: vi.fn(), delete: vi.fn() },
}));
const { mockToast } = vi.hoisted(() => ({ mockToast: { error: vi.fn() } }));

vi.mock('@/lib/api', () => ({ api: mockAxios, configureApiAuth: vi.fn() }));
vi.mock('vue-sonner', () => ({ toast: mockToast }));

import { useEnrollment } from '@/composables/useEnrollment';
import { withPinia } from '@/test/utils';

describe('useEnrollment', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    withPinia();
  });

  it('enrolls with PUT on the enrollment resource', async () => {
    mockAxios.put.mockResolvedValueOnce({ status: 204 });

    const { enroll } = useEnrollment();
    const result = await enroll(1);

    expect(result).toBe(true);
    expect(mockAxios.put).toHaveBeenCalledWith('/programs/1/enrollment');
    expect(mockToast.error).not.toHaveBeenCalled();
  });

  it('returns false and shows error toast when enrollment fails', async () => {
    mockAxios.put.mockRejectedValueOnce(new Error('Network error'));

    const { enroll } = useEnrollment();
    const result = await enroll(1);

    expect(result).toBe(false);
    expect(mockToast.error).toHaveBeenCalledWith('Failed to enroll in program');
  });

  it('unenrolls with DELETE on the enrollment resource', async () => {
    mockAxios.delete.mockResolvedValueOnce({ status: 204 });

    const { unenroll } = useEnrollment();
    const result = await unenroll(7);

    expect(result).toBe(true);
    expect(mockAxios.delete).toHaveBeenCalledWith('/programs/7/enrollment');
    expect(mockToast.error).not.toHaveBeenCalled();
  });

  it('returns false and shows error toast when unenrolling fails', async () => {
    mockAxios.delete.mockRejectedValueOnce(new Error('Network error'));

    const { unenroll } = useEnrollment();
    const result = await unenroll(7);

    expect(result).toBe(false);
    expect(mockToast.error).toHaveBeenCalledWith('Failed to unenroll from program');
  });
});
