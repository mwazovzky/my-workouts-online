import { computed, unref } from 'vue';
import { useTitle } from '@vueuse/core';

export const APP_NAME = 'My Workouts Online';

/**
 * The full document title for a page title, e.g. `Support – My Workouts Online`.
 */
export function fullPageTitle(title) {
  return title ? `${title} – ${APP_NAME}` : APP_NAME;
}

/**
 * Sets `document.title` for the current page; accepts a string, ref or getter.
 */
export function usePageTitle(title) {
  const resolved = computed(() => (typeof title === 'function' ? title() : unref(title)));

  useTitle(computed(() => fullPageTitle(resolved.value)));
}
