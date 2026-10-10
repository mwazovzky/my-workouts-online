/**
 * Helpers for RFC 9457 Problem Details responses (`application/problem+json`):
 * `{ type, title, status, detail, instance, code }`, plus `errors[]` with an
 * RFC 6901 JSON Pointer per field on a 422.
 */

/** `/activities/0/sets/1/effort_value` → `activities.0.sets.1.effort_value`. */
export function pointerToPath(pointer) {
  if (typeof pointer !== 'string' || pointer === '') return '';
  const segments = pointer.startsWith('/') ? pointer.slice(1).split('/') : pointer.split('/');
  return segments.map(segment => segment.replace(/~1/g, '/').replace(/~0/g, '~')).join('.');
}

/** The localized message of a Problem response, if the error carries one. */
export function problemDetail(error) {
  return error?.response?.data?.detail;
}

/**
 * Flattens a 422 Problem's `errors[]` into `{ 'dotted.path': firstDetail }`,
 * the shape form components bind to.
 */
export function problemFieldErrors(problem) {
  const flattened = {};
  for (const entry of Array.isArray(problem?.errors) ? problem.errors : []) {
    const path = pointerToPath(entry?.pointer);
    if (path && !(path in flattened)) {
      flattened[path] = entry.detail;
    }
  }
  return flattened;
}
