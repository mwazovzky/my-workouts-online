/**
 * Format a snake_case status string for display.
 * e.g. 'in_progress' → 'In Progress', 'completed' → 'Completed'
 *
 * @param {string} status - Raw status value
 * @returns {string} Human-friendly label
 */
export function formatStatus(status) {
  if (!status) return '';

  return status
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}
