/**
 * Get the current document locale, falling back to 'en'.
 * Reads from <html lang="...">, which Laravel sets via app()->getLocale().
 *
 * @returns {string}
 */
function getLocale() {
  return document.documentElement.lang || 'en';
}

/**
 * Format a date string for display
 * @param {string} dateString - ISO date string to format
 * @param {Object} options - Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
export function formatDate(dateString, options = {}) {
  if (!dateString) return '';

  const date = new Date(dateString);

  const defaultOptions = {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  };

  return new Intl.DateTimeFormat(getLocale(), {
    ...defaultOptions,
    ...options,
  }).format(date);
}

/**
 * Format a date string for display (date only, no time)
 * @param {string} dateString - ISO date string to format
 * @returns {string} Formatted date string
 */
export function formatDateOnly(dateString) {
  if (!dateString) return '';

  const date = new Date(dateString);

  return new Intl.DateTimeFormat(getLocale(), {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
}
