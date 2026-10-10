import { describe, expect, it } from 'vitest';
import { WEEKDAYS, difficultyLabel, effortLabel, statusLabel, weekdayLabel } from './enumLabels';

const t = key => `[${key}]`;

describe('enumLabels', () => {
  it('lists weekdays in calendar order with lowercase values', () => {
    expect(WEEKDAYS).toEqual([
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
      'sunday',
    ]);
  });

  it('maps a weekday to its capitalised catalog key', () => {
    expect(weekdayLabel(t, 'wednesday')).toBe('[Wednesday]');
    expect(weekdayLabel(t, 'Wednesday')).toBe('');
    expect(weekdayLabel(t, undefined)).toBe('');
  });

  it('maps a workout status to its catalog key', () => {
    expect(statusLabel(t, 'in_progress')).toBe('[in_progress]');
    expect(statusLabel(t, 'completed')).toBe('[completed]');
    expect(statusLabel(t, null)).toBe('');
  });

  it('labels the effort column by effort type', () => {
    expect(effortLabel(t, 'repetitions')).toBe('[Reps]');
    expect(effortLabel(t, 'duration')).toBe('[Duration]');
    expect(effortLabel(t, undefined)).toBe('[Reps]');
  });

  it('labels the difficulty column by unit', () => {
    expect(difficultyLabel(t, 'kilograms')).toBe('[Weight] ([kg])');
    expect(difficultyLabel(t, 'pounds')).toBe('[Weight] ([lbs])');
    expect(difficultyLabel(t, 'plates')).toBe('[plates]');
    expect(difficultyLabel(t, 'heart_rate_zone')).toBe('[Heart-rate zone]');
    expect(difficultyLabel(t, 'none')).toBe('');
    expect(difficultyLabel(t, null)).toBe('');
  });
});
