import { resolve } from 'node:path';
import { cwd } from 'node:process';
import { describe, expect, it } from 'vitest';
import { loadPublicCatalog } from '@/lib/catalogFiles';
import {
  buildPublicCatalog,
  CatalogError,
  groupSets,
  plannedSetsLabel,
  programEquipment,
  programTemplates,
} from '@/lib/publicCatalog';

const text = (en, ru) => ({ en: { name: en, description: `${en}.` }, ...(ru && { ru }) });

const files = () => ({
  equipment: [
    { slug: 'barbell', difficulty_unit: 'kilograms', translations: text('Barbell') },
    { slug: 'indoor-bike', difficulty_unit: 'heart_rate_zone', translations: text('Bike') },
  ],
  exercises: [
    {
      slug: 'squat',
      equipment: 'barbell',
      effort_type: 'repetitions',
      translations: text('Squat', { name: 'Присед' }),
    },
    {
      slug: 'ride',
      equipment: 'indoor-bike',
      effort_type: 'duration',
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

  it('leaves retired programs out', () => {
    const catalog = buildPublicCatalog({ ...files(), retired: { programs: [{ slug: 'mixed' }] } });

    expect(catalog.programs).toEqual([]);
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
  });
});

describe('the shipped catalog', () => {
  it('builds, with a page for every program in catalog/programs.yaml', () => {
    const { catalog, files: read } = loadPublicCatalog(resolve(cwd(), '../../catalog'));

    expect(catalog.programs.length).toBeGreaterThanOrEqual(15);
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
