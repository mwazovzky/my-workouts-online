import { useRoute } from 'vue-router';

/**
 * Ziggy-style active check: `isActive('admin.*')` matches any `admin.` route.
 */
export function useActiveRoute() {
  const route = useRoute();

  function isActive(pattern) {
    const name = String(route.name ?? '');

    return pattern.endsWith('.*') ? name.startsWith(pattern.slice(0, -1)) : name === pattern;
  }

  return { isActive };
}
