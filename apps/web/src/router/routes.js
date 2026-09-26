const idProp = route => ({ id: Number(route.params.id) });

const pages = import.meta.glob('@/pages/**/*.vue');

/**
 * Lazy page loader by path relative to `src/pages` (nested paths allowed).
 */
const page = path => {
  const loader = pages[`/src/pages/${path}.vue`];

  if (!loader) {
    throw new Error(`Unknown page: ${path}`);
  }

  return loader;
};

/**
 * Route names mirror the former Laravel route names so links read the same.
 * Meta: `guest` (signed out only), `auth`, `verified`, `admin`.
 */
export const routes = [
  { path: '/', name: 'welcome', component: page('Welcome'), meta: { guest: true } },

  // Auth
  { path: '/login', name: 'login', component: page('auth/Login'), meta: { guest: true } },
  { path: '/register', name: 'register', component: page('auth/Register'), meta: { guest: true } },
  {
    path: '/forgot-password',
    name: 'password.request',
    component: page('auth/ForgotPassword'),
    meta: { guest: true },
  },
  {
    path: '/reset-password/:token',
    name: 'password.reset',
    component: page('auth/ResetPassword'),
    meta: { guest: true },
  },
  {
    path: '/verify-email',
    name: 'verification.notice',
    component: page('auth/VerifyEmail'),
    meta: { auth: true },
  },
  {
    path: '/verify-email/:id(\\d+)/:hash',
    name: 'verification.verify',
    component: page('auth/VerifyEmailLink'),
    meta: { auth: true },
  },
  {
    path: '/confirm-password',
    name: 'password.confirm',
    component: page('auth/ConfirmPassword'),
    meta: { auth: true },
  },

  // App
  { path: '/dashboard', name: 'dashboard', component: page('Dashboard'), meta: { verified: true } },
  { path: '/about', name: 'about', component: page('About'), meta: { verified: true } },
  {
    path: '/programs',
    name: 'programs.index',
    component: page('ProgramIndex'),
    meta: { verified: true },
  },
  {
    path: '/programs/:id(\\d+)',
    name: 'programs.show',
    component: page('ProgramShow'),
    props: idProp,
    meta: { verified: true },
  },
  {
    path: '/workout-templates/:id(\\d+)',
    name: 'workout.templates.show',
    component: page('WorkoutTemplateShow'),
    props: idProp,
    meta: { verified: true },
  },
  {
    path: '/workouts',
    name: 'workouts.index',
    component: page('WorkoutIndex'),
    meta: { verified: true },
  },
  {
    path: '/workouts/:id(\\d+)',
    name: 'workouts.show',
    component: page('WorkoutShow'),
    props: idProp,
    meta: { verified: true },
  },
  {
    path: '/workouts/:id(\\d+)/edit',
    name: 'workouts.edit',
    component: page('WorkoutEdit'),
    props: idProp,
    meta: { verified: true },
  },
  { path: '/profile', name: 'profile.edit', component: page('Profile/Edit'), meta: { auth: true } },

  // Admin
  { path: '/admin', name: 'admin.index', component: page('Admin/Index'), meta: { admin: true } },
  ...['equipment', 'categories', 'exercises', 'workout-templates', 'programs'].flatMap(resource => {
    const pascal = {
      equipment: 'Equipment',
      categories: 'Category',
      exercises: 'Exercise',
      'workout-templates': 'WorkoutTemplate',
      programs: 'Program',
    }[resource];

    return [
      {
        path: `/admin/${resource}`,
        name: `admin.${resource}`,
        component: page(`Admin/${pascal}Index`),
        meta: { admin: true },
      },
      {
        path: `/admin/${resource}/create`,
        name: `admin.${resource}.create`,
        component: page(`Admin/${pascal}Create`),
        meta: { admin: true },
      },
      {
        path: `/admin/${resource}/:id(\\d+)/edit`,
        name: `admin.${resource}.edit`,
        component: page(`Admin/${pascal}Edit`),
        props: idProp,
        meta: { admin: true },
      },
    ];
  }),

  { path: '/:pathMatch(.*)*', name: 'not-found', component: page('NotFound') },
];
