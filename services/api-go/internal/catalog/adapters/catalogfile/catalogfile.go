// Package catalogfile reads the shared YAML catalog (the repository's
// catalog/ directory, ADR 0009) into a ports.RawCatalog, normalised as PHP's
// CatalogLoader normalises its data files. Values are left as YAML gives them
// so the validator can report a wrong type with its rule; only keys are
// checked here, and an unknown key is an error, so a typo cannot silently
// drop a field.
package catalogfile

import (
	"errors"
	"fmt"
	"io/fs"
	"maps"
	"slices"

	"go.yaml.in/yaml/v3"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
)

// The keys each record may have.
var allowedKeys = map[ports.Kind][]string{
	ports.KindEquipment:        {"slug", "difficulty_unit", "translations", "previous_names"},
	ports.KindCategories:       {"slug", "translations", "previous_names"},
	ports.KindExercises:        {"slug", "equipment", "effort_type", "rest_time_seconds", "categories", "translations", "previous_names"},
	ports.KindWorkoutTemplates: {"slug", "translations", "activities", "previous_names"},
	ports.KindPrograms:         {"slug", "translations", "schedule", "previous_names"},
}

var (
	activityKeys = []string{"exercise", "sets", "effort", "difficulty"}
	setKeys      = []string{"effort", "difficulty"}
	scheduleKeys = []string{"weekday", "template"}
	retiredKeys  = []string{"slug", "names"}
)

// Source reads the catalog from a directory.
type Source struct {
	dir fs.FS
}

var _ ports.CatalogSource = Source{}

// New returns a Source reading dir (os.DirFS of the catalog directory).
func New(dir fs.FS) Source {
	return Source{dir: dir}
}

// ErrNoCatalog is returned when the directory does not exist.
var ErrNoCatalog = errors.New("catalog directory does not exist")

// Load implements ports.CatalogSource.
func (s Source) Load() (ports.RawCatalog, error) {
	if _, err := fs.Stat(s.dir, "."); err != nil {
		return ports.RawCatalog{}, ErrNoCatalog
	}

	var (
		catalog ports.RawCatalog
		err     error
	)

	if catalog.Equipment, err = s.list("equipment.yaml", ports.KindEquipment); err != nil {
		return ports.RawCatalog{}, err
	}

	if catalog.Categories, err = s.list("categories.yaml", ports.KindCategories); err != nil {
		return ports.RawCatalog{}, err
	}

	if catalog.Exercises, err = s.directory("exercises", ports.KindExercises); err != nil {
		return ports.RawCatalog{}, err
	}

	if catalog.WorkoutTemplates, err = s.directory("workout-templates", ports.KindWorkoutTemplates); err != nil {
		return ports.RawCatalog{}, err
	}

	if catalog.Programs, err = s.list("programs.yaml", ports.KindPrograms); err != nil {
		return ports.RawCatalog{}, err
	}

	if catalog.Retired, err = s.retired(); err != nil {
		return ports.RawCatalog{}, err
	}

	return catalog, nil
}

// read decodes one file; a missing file is fs.ErrNotExist.
func (s Source) read(file string) (any, error) {
	data, err := fs.ReadFile(s.dir, file)
	if err != nil {
		return nil, err
	}

	var value any
	if err := yaml.Unmarshal(data, &value); err != nil {
		return nil, fmt.Errorf("catalog file %s: %w", file, err)
	}

	return value, nil
}

// list reads a required file holding a list of records.
func (s Source) list(file string, kind ports.Kind) ([]any, error) {
	value, err := s.read(file)
	if errors.Is(err, fs.ErrNotExist) {
		return nil, fmt.Errorf("catalog file %s does not exist", file)
	}

	if err != nil {
		return nil, err
	}

	records, ok := value.([]any)
	if !ok && value != nil {
		return nil, fmt.Errorf("catalog file %s must hold a list of records", file)
	}

	for i, record := range records {
		if records[i], err = normalise(kind, record); err != nil {
			return nil, fmt.Errorf("catalog file %s: %w", file, err)
		}
	}

	return records, nil
}

// directory merges the files of a directory in file name order.
func (s Source) directory(dir string, kind ports.Kind) ([]any, error) {
	files, err := fs.Glob(s.dir, dir+"/*.yaml")
	if err != nil {
		return nil, err
	}

	if len(files) == 0 {
		return nil, fmt.Errorf("catalog directory %s has no data files", dir)
	}

	slices.Sort(files)

	var records []any

	for _, file := range files {
		list, err := s.list(file, kind)
		if err != nil {
			return nil, err
		}

		records = append(records, list...)
	}

	return records, nil
}

// retired reads the optional retired.yaml.
func (s Source) retired() (map[string]any, error) {
	value, err := s.read("retired.yaml")
	if errors.Is(err, fs.ErrNotExist) || (err == nil && value == nil) {
		return map[string]any{}, nil
	}

	if err != nil {
		return nil, err
	}

	retired, ok := value.(map[string]any)
	if !ok {
		return nil, errors.New("catalog file retired.yaml must map record types to lists")
	}

	for kind, entries := range retired {
		list, ok := entries.([]any)
		if !ok && entries != nil {
			return nil, fmt.Errorf("catalog file retired.yaml: %s must be a list", kind)
		}

		for _, entry := range list {
			if err := checkKeys(entry, retiredKeys); err != nil {
				return nil, fmt.Errorf("catalog file retired.yaml: %s: %w", kind, err)
			}
		}
	}

	return retired, nil
}

// normalise fills defaults and expands the set shorthand, as PHP's
// CatalogLoader::normaliseRecord does. A record that is not a mapping is left
// for the validator.
func normalise(kind ports.Kind, value any) (any, error) {
	record, ok := value.(map[string]any)
	if !ok {
		return value, nil
	}

	if err := checkKeys(record, allowedKeys[kind]); err != nil {
		return nil, fmt.Errorf("%s %s: %w", kind, label(record), err)
	}

	if _, ok := record["previous_names"]; !ok {
		record["previous_names"] = []any{}
	}

	if _, ok := record["rest_time_seconds"]; !ok && kind == ports.KindExercises {
		record["rest_time_seconds"] = nil
	}

	if schedule, ok := record["schedule"].([]any); ok && kind == ports.KindPrograms {
		for _, entry := range schedule {
			if err := checkKeys(entry, scheduleKeys); err != nil {
				return nil, fmt.Errorf("%s %s: schedule: %w", kind, label(record), err)
			}
		}
	}

	if activities, ok := record["activities"].([]any); ok && kind == ports.KindWorkoutTemplates {
		for i, activity := range activities {
			var err error
			if activities[i], err = normaliseActivity(activity); err != nil {
				return nil, fmt.Errorf("%s %s: activity %d: %w", kind, label(record), i+1, err)
			}
		}
	}

	return record, nil
}

// normaliseActivity expands `sets: 3, effort: 10` into three explicit sets
// and gives every set both keys.
func normaliseActivity(value any) (any, error) {
	activity, ok := value.(map[string]any)
	if !ok {
		return value, nil
	}

	if err := checkKeys(activity, activityKeys); err != nil {
		return nil, err
	}

	sets := activity["sets"]

	switch v := sets.(type) {
	case int:
		expanded := []any{}
		for range max(v, 0) {
			expanded = append(expanded, map[string]any{"effort": activity["effort"], "difficulty": activity["difficulty"]})
		}

		sets = expanded
	case []any:
		explicit := make([]any, len(v))

		for i, set := range v {
			m, ok := set.(map[string]any)
			if !ok {
				explicit[i] = set

				continue
			}

			if err := checkKeys(m, setKeys); err != nil {
				return nil, fmt.Errorf("set %d: %w", i+1, err)
			}

			explicit[i] = map[string]any{"effort": m["effort"], "difficulty": m["difficulty"]}
		}

		sets = explicit
	}

	return map[string]any{"exercise": activity["exercise"], "sets": sets}, nil
}

func checkKeys(value any, allowed []string) error {
	m, ok := value.(map[string]any)
	if !ok {
		return nil
	}

	for _, key := range slices.Sorted(maps.Keys(m)) {
		if !slices.Contains(allowed, key) {
			return fmt.Errorf("unknown key %q", key)
		}
	}

	return nil
}

func label(record map[string]any) string {
	if slug, ok := record["slug"].(string); ok && slug != "" {
		return "[" + slug + "]"
	}

	return "(no slug)"
}
