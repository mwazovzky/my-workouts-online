import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockPage } = vi.hoisted(() => ({
  mockPage: {
    props: {},
  },
}));

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => mockPage,
}));

import { useTranslation } from '@/composables/useTranslation';

describe('useTranslation', () => {
  beforeEach(() => {
    mockPage.props = {
      translations: {},
    };
  });

  it('returns translated strings with placeholder replacements', () => {
    mockPage.props = {
      translations: {
        Greeting: 'Hello, :name!',
      },
    };

    const { t } = useTranslation();

    expect(t('Greeting', { name: 'Alex' })).toBe('Hello, Alex!');
  });

  it('falls back to the original key when a translation is missing', () => {
    const { t } = useTranslation();

    expect(t('Missing Key')).toBe('Missing Key');
  });
});
