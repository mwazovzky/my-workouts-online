import { computed, unref } from 'vue';
import { useTitle } from '@vueuse/core';

const APP_NAME = 'My Workouts';

/**
 * Sets `document.title` for the current page; accepts a string, ref or getter.
 */
export function usePageTitle(title) {
  const resolved = computed(() => (typeof title === 'function' ? title() : unref(title)));

  useTitle(resolved, { titleTemplate: `%s - ${APP_NAME}` });
}
