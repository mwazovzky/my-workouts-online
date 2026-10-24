import { DEFAULT_LOCALE, LOCALES } from './crawl.js';
import { WEEKDAYS } from './enumLabels.js';

/**
 * The part of the shipped catalog (`catalog/` at the repository root) the
 * public pages show, built at build time by `src/lib/catalogFiles.js` and
 * served to the pages as the `virtual:public-catalog` module.
 *
 * Shape: `programs` in file order with `slug`, `name`, `description` and a
 * `schedule` of `{ weekday, template }` sorted by weekday; `workoutTemplates`,
 * `exercises` and `equipment` by slug, holding only what a program uses.
 * Texts are `{ en, ru }`, every language filled (English when a translation
 * is missing). Template activities are `{ exercise, sets: [{ effort,
 * difficulty }] }`, the `sets: N` shorthand expanded.
 */
export class CatalogError extends Error {
  constructor(message) {
    super(`Public catalog: ${message}`);
    this.name = 'CatalogError';
  }
}

const isText = value => typeof value === 'string' && value.trim() !== '';
const isPositiveNumber = value => typeof value === 'number' && Number.isFinite(value) && value > 0;

function localized(record, field, label) {
  const source = record.translations?.[DEFAULT_LOCALE]?.[field];

  if (!isText(source)) {
    throw new CatalogError(`${label} has no English ${field}`);
  }

  return Object.fromEntries(
    LOCALES.map(locale => {
      const value = record.translations?.[locale]?.[field];
      return [locale, isText(value) ? value : source];
    })
  );
}

function bySlug(records, type, retired) {
  const map = new Map();

  for (const record of records ?? []) {
    if (!isText(record?.slug)) {
      throw new CatalogError(`a ${type} has no slug`);
    }
    if (map.has(record.slug)) {
      throw new CatalogError(`${type} "${record.slug}" is listed twice`);
    }
    if (!retired.has(record.slug)) {
      map.set(record.slug, record);
    }
  }

  return map;
}

function expandSets(activity, label) {
  const { sets, effort, difficulty } = activity;

  if (Array.isArray(sets)) {
    if (sets.length === 0) {
      throw new CatalogError(`${label} has no sets`);
    }
    return sets.map((set, index) => plannedSet(set, `${label}, set ${index + 1}`));
  }

  if (!Number.isInteger(sets) || sets < 1) {
    throw new CatalogError(`${label} needs "sets" as a positive whole number or a list`);
  }

  return Array.from({ length: sets }, () => plannedSet({ effort, difficulty }, label));
}

function plannedSet({ effort, difficulty }, label) {
  if (!isPositiveNumber(effort)) {
    throw new CatalogError(`${label} needs a positive effort`);
  }
  if (difficulty !== undefined && difficulty !== null && !isPositiveNumber(difficulty)) {
    throw new CatalogError(`${label} has a difficulty that is not a positive number`);
  }

  return { effort, difficulty: difficulty ?? null };
}

/**
 * Builds the public catalog from the parsed catalog files. Throws a
 * `CatalogError` naming the record when a program refers to a workout
 * template, exercise or equipment item that does not exist (or is retired),
 * or a field it shows is missing or malformed. Retired programs are left out.
 */
export function buildPublicCatalog({
  programs = [],
  workoutTemplates = [],
  exercises = [],
  equipment = [],
  retired = {},
}) {
  const retiredSlugs = type => new Set((retired?.[type] ?? []).map(record => record.slug));
  const templateRecords = bySlug(
    workoutTemplates,
    'workout template',
    retiredSlugs('workout_templates')
  );
  const exerciseRecords = bySlug(exercises, 'exercise', retiredSlugs('exercises'));
  const equipmentRecords = bySlug(equipment, 'equipment item', retiredSlugs('equipment'));
  const programRecords = bySlug(programs, 'program', retiredSlugs('programs'));

  const catalog = { programs: [], workoutTemplates: {}, exercises: {}, equipment: {} };

  const useEquipment = (slug, label) => {
    const record = equipmentRecords.get(slug);
    if (!record) {
      throw new CatalogError(`${label} uses unknown equipment "${slug}"`);
    }
    catalog.equipment[slug] ??= {
      name: localized(record, 'name', `equipment "${slug}"`),
      difficultyUnit: record.difficulty_unit,
    };
  };

  const useExercise = (slug, label) => {
    const record = exerciseRecords.get(slug);
    if (!record) {
      throw new CatalogError(`${label} uses unknown exercise "${slug}"`);
    }
    if (!catalog.exercises[slug]) {
      const exerciseLabel = `exercise "${slug}"`;
      useEquipment(record.equipment, exerciseLabel);
      catalog.exercises[slug] = {
        name: localized(record, 'name', exerciseLabel),
        effortType: record.effort_type === 'duration' ? 'duration' : 'repetitions',
        equipment: record.equipment,
      };
    }
  };

  const useTemplate = (slug, label) => {
    const record = templateRecords.get(slug);
    if (!record) {
      throw new CatalogError(`${label} schedules unknown workout template "${slug}"`);
    }
    if (catalog.workoutTemplates[slug]) {
      return;
    }

    const templateLabel = `workout template "${slug}"`;
    if (!Array.isArray(record.activities) || record.activities.length === 0) {
      throw new CatalogError(`${templateLabel} has no activities`);
    }

    catalog.workoutTemplates[slug] = {
      name: localized(record, 'name', templateLabel),
      description: localized(record, 'description', templateLabel),
      activities: record.activities.map((activity, index) => {
        const activityLabel = `${templateLabel}, activity ${index + 1}`;
        useExercise(activity?.exercise, templateLabel);
        return { exercise: activity.exercise, sets: expandSets(activity, activityLabel) };
      }),
    };
  };

  for (const [slug, record] of programRecords) {
    const label = `program "${slug}"`;

    if (!Array.isArray(record.schedule) || record.schedule.length === 0) {
      throw new CatalogError(`${label} has no schedule`);
    }

    const schedule = record.schedule.map(({ weekday, template }) => {
      if (!WEEKDAYS.includes(weekday)) {
        throw new CatalogError(`${label} has an unknown weekday "${weekday}"`);
      }
      useTemplate(template, label);
      return { weekday, template };
    });

    catalog.programs.push({
      slug,
      name: localized(record, 'name', label),
      description: localized(record, 'description', label),
      schedule: schedule.sort((a, b) => WEEKDAYS.indexOf(a.weekday) - WEEKDAYS.indexOf(b.weekday)),
    });
  }

  return catalog;
}

/**
 * The workout templates a program uses, once each, in schedule order.
 */
export function programTemplates(catalog, program) {
  return [...new Set(program.schedule.map(day => day.template))].map(slug => ({
    slug,
    ...catalog.workoutTemplates[slug],
  }));
}

/**
 * The equipment a program needs, by slug, in order of first use.
 */
export function programEquipment(catalog, program) {
  const slugs = programTemplates(catalog, program).flatMap(template =>
    template.activities.map(activity => catalog.exercises[activity.exercise].equipment)
  );

  return [...new Set(slugs)].map(slug => ({ slug, ...catalog.equipment[slug] }));
}

/**
 * A template activity's sets as runs of identical sets, for "3 × 10".
 */
export function groupSets(sets) {
  return sets.reduce((groups, set) => {
    const last = groups.at(-1);

    if (last && last.effort === set.effort && last.difficulty === set.difficulty) {
      last.count += 1;
    } else {
      groups.push({ count: 1, effort: set.effort, difficulty: set.difficulty });
    }

    return groups;
  }, []);
}

function durationLabel(t, seconds) {
  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;
  const parts = [
    minutes > 0 ? t(':count min', { count: minutes }) : null,
    rest > 0 ? t(':count s', { count: rest }) : null,
  ];

  return parts.filter(Boolean).join(' ');
}

/**
 * A template activity's planned sets as text: "3 × 10 reps", "45 min (zone 2)",
 * "12 reps, 10 reps, 8 reps". Weights are never planned, so only heart-rate
 * zones are shown as difficulty.
 */
export function plannedSetsLabel(t, sets, effortType, difficultyUnit) {
  return groupSets(sets)
    .map(({ count, effort, difficulty }) => {
      let label =
        effortType === 'duration' ? durationLabel(t, effort) : t(':count reps', { count: effort });

      if (difficultyUnit === 'heart_rate_zone' && difficulty) {
        label += ` (${t('zone :zone', { zone: difficulty })})`;
      }

      return count > 1 ? `${count} × ${label}` : label;
    })
    .join(', ');
}
