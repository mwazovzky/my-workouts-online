import { afterEach, describe, expect, it } from 'vitest';

import { formatDate, formatDateOnly } from '@/utils/date';

describe('date utils', () => {
  afterEach(() => {
    document.documentElement.lang = 'en';
  });

  it('formats a date-only value using the current document locale', () => {
    document.documentElement.lang = 'en';

    expect(formatDateOnly('2024-01-15T12:00:00Z')).toBe('Jan 15, 2024');
  });

  it('returns an empty string when no date is provided', () => {
    expect(formatDateOnly('')).toBe('');
    expect(formatDate('')).toBe('');
  });
});
