import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const { mockApi } = vi.hoisted(() => ({ mockApi: { get: vi.fn() } }));
vi.mock('@/lib/api', () => ({ api: mockApi, configureApiAuth: vi.fn() }));

import { useMetaStore } from '@/stores/meta';

describe('meta store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('loads locales and theme options once', async () => {
    mockApi.get.mockResolvedValue({
      data: {
        data: {
          available_locales: { en: 'English', ru: 'Русский' },
          theme_preferences: ['light', 'dark'],
        },
      },
    });

    const meta = useMetaStore();
    expect(meta.loaded).toBe(false);

    await meta.load();
    await meta.load();

    expect(mockApi.get).toHaveBeenCalledTimes(1);
    expect(mockApi.get).toHaveBeenCalledWith('/meta');
    expect(meta.availableLocales).toEqual({ en: 'English', ru: 'Русский' });
    expect(meta.themePreferences).toEqual(['light', 'dark']);
    expect(meta.loaded).toBe(true);
  });

  it('keeps defaults and stays unloaded when the request fails', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('offline'));

    const meta = useMetaStore();
    await expect(meta.load()).rejects.toThrow('offline');

    expect(meta.loaded).toBe(false);
    expect(meta.availableLocales).toEqual({ en: 'English' });
  });
});
