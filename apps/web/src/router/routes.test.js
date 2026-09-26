import { describe, expect, it } from 'vitest';
import { routes } from '@/router/routes';

describe('route table', () => {
  it('has unique names and a catch-all last', () => {
    const names = routes.map(r => r.name);

    expect(new Set(names).size).toBe(names.length);
    expect(routes.at(-1).name).toBe('not-found');
  });

  it('resolves every page component lazily', async () => {
    for (const route of routes) {
      expect(typeof route.component).toBe('function');
      const module = await route.component();
      expect(module.default).toBeTruthy();
    }
  }, 30000);

  it('coerces id params to numbers', () => {
    const withId = routes.filter(r => typeof r.props === 'function');

    expect(withId.length).toBeGreaterThan(0);
    for (const route of withId) {
      expect(route.props({ params: { id: '42' } })).toEqual({ id: 42 });
    }
  });

  it('mirrors the former Laravel route names', () => {
    const names = routes.map(r => r.name);

    for (const expected of [
      'welcome',
      'login',
      'register',
      'password.request',
      'password.reset',
      'verification.notice',
      'verification.verify',
      'password.confirm',
      'dashboard',
      'programs.index',
      'programs.show',
      'workout.templates.show',
      'workouts.index',
      'workouts.show',
      'workouts.edit',
      'profile.edit',
      'about',
      'admin.index',
      'admin.equipment',
      'admin.equipment.create',
      'admin.equipment.edit',
      'admin.programs.edit',
    ]) {
      expect(names).toContain(expected);
    }
  });
});
