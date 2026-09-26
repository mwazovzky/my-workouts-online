import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';

/**
 * Renders RouterLink as a plain anchor whose href is the route name (or the
 * literal path), which keeps href assertions readable in component tests.
 */
export const RouterLinkStub = {
  props: ['to'],
  template: '<a :href="hrefFor(to)"><slot /></a>',
  methods: {
    hrefFor(to) {
      if (typeof to === 'string') {
        return to;
      }
      const id = to.params?.id;
      return id === undefined ? `/${to.name}` : `/${to.name}/${id}`;
    },
  },
};

/**
 * Fresh Pinia with an optional signed-in user, returned as mount options.
 */
export function withPinia(user = null) {
  const pinia = createPinia();
  setActivePinia(pinia);
  if (user) {
    useAuthStore().user = user;
  }
  return pinia;
}
