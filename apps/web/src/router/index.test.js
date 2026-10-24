import { describe, expect, it } from 'vitest';
import { createAppRouter } from '@/router';
import { routes } from '@/router/routes';

describe('createAppRouter', () => {
  it('registers every route and restores scroll position or scrolls to a hash', () => {
    const router = createAppRouter();

    expect(router.getRoutes()).toHaveLength(routes.length);
    expect(router.options.scrollBehavior(null, null, { left: 0, top: 120 })).toEqual({
      left: 0,
      top: 120,
    });
    expect(router.options.scrollBehavior({ hash: '' }, null, null)).toEqual({ top: 0 });
    expect(router.options.scrollBehavior({ hash: '#gym-full-body-a' }, null, null)).toEqual({
      el: '#gym-full-body-a',
    });
  });
});
