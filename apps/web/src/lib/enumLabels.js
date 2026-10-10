/**
 * Localized labels for API enum values. The API sends only the enum value;
 * each helper takes the `t()` from `useTranslation` and returns display text.
 */

const WEEKDAY_KEYS = {
  monday: 'Monday',
  tuesday: 'Tuesday',
  wednesday: 'Wednesday',
  thursday: 'Thursday',
  friday: 'Friday',
  saturday: 'Saturday',
  sunday: 'Sunday',
};

export const WEEKDAYS = Object.keys(WEEKDAY_KEYS);

export function weekdayLabel(t, weekday) {
  return WEEKDAY_KEYS[weekday] ? t(WEEKDAY_KEYS[weekday]) : '';
}

export function statusLabel(t, status) {
  return status ? t(status) : '';
}

export function effortLabel(t, effortType) {
  return effortType === 'duration' ? t('Duration') : t('Reps');
}

export function difficultyLabel(t, difficultyUnit) {
  switch (difficultyUnit) {
    case 'kilograms':
      return `${t('Weight')} (${t('kg')})`;
    case 'pounds':
      return `${t('Weight')} (${t('lbs')})`;
    case 'plates':
      return t('plates');
    case 'heart_rate_zone':
      return t('Heart-rate zone');
    default:
      return '';
  }
}
