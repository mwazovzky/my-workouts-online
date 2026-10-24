import { resolve } from 'node:path';
import { cwd } from 'node:process';
import { describe, expect, it } from 'vitest';
import { loadPublicCatalog } from '@/lib/catalogFiles';
import {
  buildPublicCatalog,
  CatalogError,
  exercisesByCategory,
  exerciseUsage,
  groupSets,
  plannedSetsLabel,
  programEquipment,
  programTemplates,
  relatedExercises,
} from '@/lib/publicCatalog';

const text = (en, ru) => ({ en: { name: en, description: `${en}.` }, ...(ru && { ru }) });

const files = () => ({
  categories: [
    { slug: 'legs', translations: text('Legs', { name: 'Ноги' }) },
    { slug: 'arms', translations: text('Arms') },
    { slug: 'cycling', translations: text('Cycling') },
    { slug: 'unused', translations: text('Unused') },
  ],
  equipment: [
    { slug: 'barbell', difficulty_unit: 'kilograms', translations: text('Barbell') },
    { slug: 'indoor-bike', difficulty_unit: 'heart_rate_zone', translations: text('Bike') },
  ],
  exercises: [
    {
      slug: 'squat',
      equipment: 'barbell',
      effort_type: 'repetitions',
      categories: ['legs', 'arms'],
      translations: text('Squat', { name: 'Присед' }),
    },
    {
      slug: 'lunge',
      equipment: 'barbell',
      effort_type: 'repetitions',
      categories: ['legs'],
      translations: text('Lunge'),
    },
    {
      slug: 'ride',
      equipment: 'indoor-bike',
      effort_type: 'duration',
      categories: ['cycling'],
      translations: text('Ride'),
    },
  ],
  workoutTemplates: [
    {
      slug: 'legs',
      translations: text('Legs'),
      activities: [{ exercise: 'squat', sets: 3, effort: 5 }],
    },
    {
      slug: 'spin',
      translations: text('Spin'),
      activities: [{ exercise: 'ride', sets: [{ effort: 1800, difficulty: 2 }, { effort: 300 }] }],
    },
  ],
  programs: [
    {
      slug: 'mixed',
      translations: text('Mixed', { name: 'Смешанная', description: 'Описание.' }),
      schedule: [
        { weekday: 'friday', template: 'spin' },
        { weekday: 'monday', template: 'legs' },
      ],
    },
  ],
});

const fail = (change, message) => {
  const sources = files();
  change(sources);

  expect(() => buildPublicCatalog(sources)).toThrow(CatalogError);
  expect(() => buildPublicCatalog(sources)).toThrow(message);
};

describe('buildPublicCatalog', () => {
  it('expands the sets shorthand and keeps listed sets', () => {
    const catalog = buildPublicCatalog(files());

    expect(catalog.workoutTemplates.legs.activities).toEqual([
      { exercise: 'squat', sets: Array(3).fill({ effort: 5, difficulty: null }) },
    ]);
    expect(catalog.workoutTemplates.spin.activities[0].sets).toEqual([
      { effort: 1800, difficulty: 2 },
      { effort: 300, difficulty: null },
    ]);
  });

  it('fills every language, falling back to English', () => {
    const catalog = buildPublicCatalog(files());

    expect(catalog.programs[0].name).toEqual({ en: 'Mixed', ru: 'Смешанная' });
    expect(catalog.exercises.squat.name).toEqual({ en: 'Squat', ru: 'Присед' });
    expect(catalog.exercises.ride.name).toEqual({ en: 'Ride', ru: 'Ride' });
    expect(catalog.workoutTemplates.legs.description).toEqual({ en: 'Legs.', ru: 'Legs.' });
  });

  it('orders the schedule by weekday and keeps only what programs use', () => {
    const sources = files();
    sources.workoutTemplates.push({
      slug: 'unused',
      translations: text('Unused'),
      activities: [{ exercise: 'squat', sets: 1, effort: 1 }],
    });
    const catalog = buildPublicCatalog(sources);

    expect(catalog.programs[0].schedule.map(day => day.weekday)).toEqual(['monday', 'friday']);
    expect(Object.keys(catalog.workoutTemplates).sort()).toEqual(['legs', 'spin']);
    expect(catalog.equipment['indoor-bike'].difficultyUnit).toBe('heart_rate_zone');
  });

  it('leaves retired programs and exercises out', () => {
    const catalog = buildPublicCatalog({
      ...files(),
      retired: { programs: [{ slug: 'mixed' }], exercises: [{ slug: 'lunge' }] },
    });

    expect(catalog.programs).toEqual([]);
    expect(Object.keys(catalog.exercises)).toEqual(['squat', 'ride']);
  });

  it('keeps every active exercise, with its description and categories, in file order', () => {
    const catalog = buildPublicCatalog(files());

    expect(Object.keys(catalog.exercises)).toEqual(['squat', 'lunge', 'ride']);
    expect(catalog.exercises.squat).toMatchObject({
      description: { en: 'Squat.', ru: 'Squat.' },
      effortType: 'repetitions',
      equipment: 'barbell',
      categories: ['legs', 'arms'],
    });
    expect(Object.keys(catalog.categories)).toEqual(['legs', 'arms', 'cycling']);
    expect(catalog.categories.legs.name).toEqual({ en: 'Legs', ru: 'Ноги' });
  });

  it('rejects references to records that do not exist or are retired', () => {
    fail(
      sources => (sources.programs[0].schedule[0].template = 'nope'),
      'program "mixed" schedules unknown workout template "nope"'
    );
    fail(
      sources => (sources.workoutTemplates[0].activities[0].exercise = 'nope'),
      'workout template "legs" uses unknown exercise "nope"'
    );
    fail(
      sources => (sources.exercises[0].equipment = 'nope'),
      'exercise "squat" uses unknown equipment "nope"'
    );
    fail(
      sources => (sources.retired = { exercises: [{ slug: 'ride' }] }),
      'workout template "spin" uses unknown exercise "ride"'
    );
    fail(
      sources => (sources.exercises[1].categories = ['nope']),
      'exercise "lunge" uses unknown category "nope"'
    );
  });

  it('rejects malformed fields it shows', () => {
    fail(sources => delete sources.programs[0].translations.en.name, 'has no English name');
    fail(sources => (sources.programs[0].schedule[0].weekday = 'Friday'), 'unknown weekday');
    fail(sources => (sources.workoutTemplates[0].activities[0].sets = 0), 'positive whole number');
    fail(
      sources => (sources.workoutTemplates[0].activities[0].effort = '5'),
      'needs a positive effort'
    );
    fail(sources => sources.programs.push(files().programs[0]), 'listed twice');
    fail(sources => (sources.exercises[1].categories = []), 'exercise "lunge" has no categories');
    fail(
      sources => delete sources.exercises[1].translations.en.description,
      'exercise "lunge" has no English description'
    );
  });
});

describe('the shipped catalog', () => {
  it('builds, with every program and exercise', () => {
    const { catalog, files: read } = loadPublicCatalog(resolve(cwd(), '../../catalog'));

    expect(catalog.programs.length).toBeGreaterThanOrEqual(15);
    expect(Object.keys(catalog.exercises).length).toBeGreaterThanOrEqual(137);
    expect(read.some(file => file.endsWith('workout-templates/gym.yaml'))).toBe(true);
  });
});

describe('program helpers', () => {
  const catalog = buildPublicCatalog(files());
  const [program] = catalog.programs;
  const t = (key, replacements = {}) =>
    Object.entries(replacements).reduce(
      (result, [name, value]) => result.replaceAll(`:${name}`, value),
      key
    );

  it('lists templates once in schedule order and the equipment they need', () => {
    expect(programTemplates(catalog, program).map(template => template.slug)).toEqual([
      'legs',
      'spin',
    ]);
    expect(programEquipment(catalog, program).map(item => item.slug)).toEqual([
      'barbell',
      'indoor-bike',
    ]);
  });

  it('describes planned sets', () => {
    expect(groupSets([{ effort: 5 }, { effort: 5 }, { effort: 3 }])).toEqual([
      { count: 2, effort: 5, difficulty: undefined },
      { count: 1, effort: 3, difficulty: undefined },
    ]);
    expect(
      plannedSetsLabel(t, Array(3).fill({ effort: 10, difficulty: null }), 'repetitions')
    ).toBe('3 × 10 reps');
    expect(
      plannedSetsLabel(
        t,
        [
          { effort: 2700, difficulty: 2 },
          { effort: 90, difficulty: null },
        ],
        'duration',
        'heart_rate_zone'
      )
    ).toBe('45 min (zone 2), 1 min 30 s');
    expect(plannedSetsLabel(t, [{ effort: 30, difficulty: 20 }], 'duration', 'kilograms')).toBe(
      '30 s'
    );
  });
});

describe('exercise helpers', () => {
  const catalog = buildPublicCatalog(files());

  it('lists the programs and workouts that use an exercise', () => {
    expect(exerciseUsage(catalog, 'squat')).toEqual([
      { program: catalog.programs[0], templates: ['legs'] },
    ]);
    expect(exerciseUsage(catalog, 'lunge')).toEqual([]);
  });

  it('finds exercises with the same main category', () => {
    expect(relatedExercises(catalog, 'squat')).toEqual(['lunge']);
    expect(relatedExercises(catalog, 'ride')).toEqual([]);
  });

  it('groups exercises by main category, leaving out empty groups', () => {
    expect(exercisesByCategory(catalog)).toEqual([
      { category: 'legs', exercises: ['squat', 'lunge'] },
      { category: 'cycling', exercises: ['ride'] },
    ]);
  });
});
