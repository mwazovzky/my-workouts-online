import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({ mockAxios: { post: vi.fn() } }));
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

  it('returns true when enrollment succeeds', async () => {
    mockAxios.post.mockResolvedValueOnce({});

    const { enroll } = useEnrollment();
    const result = await enroll(1);

    expect(result).toBe(true);
    expect(mockToast.error).not.toHaveBeenCalled();
  });

  it('returns false and shows error toast when enrollment fails', async () => {
    mockAxios.post.mockRejectedValueOnce(new Error('Network error'));

    const { enroll } = useEnrollment();
    const result = await enroll(1);

    expect(result).toBe(false);
    expect(mockToast.error).toHaveBeenCalledOnce();
  });
});
