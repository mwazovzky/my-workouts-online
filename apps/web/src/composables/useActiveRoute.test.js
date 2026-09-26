import { describe, expect, it, vi } from 'vitest';

const { mockRoute } = vi.hoisted(() => ({ mockRoute: { name: 'admin.exercises.edit' } }));
vi.mock('vue-router', () => ({ useRoute: () => mockRoute }));

import { useActiveRoute } from '@/composables/useActiveRoute';

describe('useActiveRoute', () => {
  it('matches exact names and wildcard prefixes', () => {
    const { isActive } = useActiveRoute();

    expect(isActive('admin.exercises.edit')).toBe(true);
    expect(isActive('admin.*')).toBe(true);
    expect(isActive('programs.*')).toBe(false);
    expect(isActive('dashboard')).toBe(false);
  });

  it('treats a missing route name as empty', () => {
    mockRoute.name = undefined;

    expect(useActiveRoute().isActive('admin.*')).toBe(false);
  });
});
