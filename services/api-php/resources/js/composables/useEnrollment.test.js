import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({ mockAxios: { post: vi.fn() } }));
const { mockToast } = vi.hoisted(() => ({ mockToast: { error: vi.fn() } }));

vi.mock('axios', () => ({ default: mockAxios }));
vi.mock('vue-sonner', () => ({ toast: mockToast }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { translations: {} } }) }));

import { useEnrollment } from '@/composables/useEnrollment';

describe('useEnrollment', () => {
  beforeEach(() => {
    vi.clearAllMocks();
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
