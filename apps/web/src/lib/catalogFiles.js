// Build-time only (Vite config, Vitest): reads the shipped catalog from disk.
// The browser gets the result through the `virtual:public-catalog` module.
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { cwd, env } from 'node:process';
import yaml from 'js-yaml';
import { buildPublicCatalog } from './publicCatalog.js';

export const PUBLIC_CATALOG_MODULE = 'virtual:public-catalog';

/**
 * `CATALOG_PATH` (the web image sets it), else `catalog/` at the repository
 * root, two levels above `apps/web`.
 */
export const catalogPath = () => env.CATALOG_PATH || resolve(cwd(), '../../catalog');

function readYaml(file) {
  try {
    return yaml.load(readFileSync(file, 'utf8'), { schema: yaml.CORE_SCHEMA, filename: file });
  } catch (error) {
    throw new Error(`Public catalog: cannot read ${file}: ${error.message}`, { cause: error });
  }
}

/**
 * The records of a catalog file, or of every file in a directory in file-name
 * order, with the files read.
 */
function readRecords(dir, name) {
  const directory = join(dir, name);
  const files = existsSync(`${directory}.yaml`)
    ? [`${directory}.yaml`]
    : readdirSync(directory)
        .filter(file => file.endsWith('.yaml'))
        .sort()
        .map(file => join(directory, file));

  return { records: files.flatMap(file => readYaml(file) ?? []), files };
}

/**
 * Reads the catalog in `dir` and builds the public catalog
 * (`src/lib/publicCatalog.js`). Returns it with the files read, so the dev
 * server can watch them.
 */
export function loadPublicCatalog(dir = catalogPath()) {
  if (!existsSync(dir)) {
    throw new Error(`Public catalog: no catalog at ${dir} (set CATALOG_PATH)`);
  }

  const sources = {
    programs: readRecords(dir, 'programs'),
    workoutTemplates: readRecords(dir, 'workout-templates'),
    exercises: readRecords(dir, 'exercises'),
    equipment: readRecords(dir, 'equipment'),
    categories: readRecords(dir, 'categories'),
  };
  const retiredFile = join(dir, 'retired.yaml');
  const retired = existsSync(retiredFile) ? (readYaml(retiredFile) ?? {}) : {};

  return {
    catalog: buildPublicCatalog({
      ...Object.fromEntries(Object.entries(sources).map(([key, { records }]) => [key, records])),
      retired,
    }),
    files: [...Object.values(sources).flatMap(source => source.files), retiredFile],
  };
}

/**
 * Serves the public catalog as `virtual:public-catalog` (a default-exported
 * object). The pages that import it are lazy-loaded, so the data stays out of
 * the main bundle.
 */
export function publicCatalogPlugin(dir) {
  const resolvedId = `\0${PUBLIC_CATALOG_MODULE}`;

  return {
    name: 'public-catalog',
    resolveId(id) {
      return id === PUBLIC_CATALOG_MODULE ? resolvedId : null;
    },
    load(id) {
      if (id !== resolvedId) {
        return null;
      }

      const { catalog, files } = loadPublicCatalog(dir);
      files.forEach(file => this.addWatchFile(file));

      return `export default ${JSON.stringify(catalog)};`;
    },
  };
}
