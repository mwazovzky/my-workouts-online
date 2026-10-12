package catalogfile_test

import (
	"errors"
	"os"
	"strings"
	"testing"
	"testing/fstest"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/catalogfile"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
)

// The repository's catalog directories, from this package.
const (
	shippedDir = "../../../../../../catalog"
	validDir   = shippedDir + "/testdata/valid"
)

func load(t *testing.T, dir string) ports.RawCatalog {
	t.Helper()

	catalog, err := catalogfile.New(os.DirFS(dir)).Load()
	if err != nil {
		t.Fatal(err)
	}

	return catalog
}

func slugsOf(records []any) []string {
	var slugs []string
	for _, r := range records {
		slugs = append(slugs, r.(map[string]any)["slug"].(string))
	}

	return slugs
}

// Mirrors CatalogLoaderTest::it_loads_every_record_type and
// ::it_merges_directory_files_in_file_name_order.
func TestLoadReadsEveryTypeAndMergesDirectoriesInFileNameOrder(t *testing.T) {
	t.Parallel()

	catalog := load(t, validDir)

	got := map[string][]string{
		"equipment":         slugsOf(catalog.Equipment),
		"categories":        slugsOf(catalog.Categories),
		"exercises":         slugsOf(catalog.Exercises),
		"workout_templates": slugsOf(catalog.WorkoutTemplates),
		"programs":          slugsOf(catalog.Programs),
	}
	want := map[string][]string{
		"equipment":  {"barbell", "bodyweight", "indoor-bike"},
		"categories": {"chest", "legs", "cardio"},
		// cardio.yaml before strength.yaml
		"exercises":         {"easy-ride", "bench-press", "squat", "push-up"},
		"workout_templates": {"easy-spin", "full-body"},
		"programs":          {"starter"},
	}

	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("slugs (-want +got):\n%s", diff)
	}
}

// Mirrors CatalogLoaderTest::it_fills_optional_fields_with_defaults.
func TestLoadFillsOptionalFieldsWithDefaults(t *testing.T) {
	t.Parallel()

	catalog := load(t, validDir)
	easyRide := catalog.Exercises[0].(map[string]any)
	benchPress := catalog.Exercises[1].(map[string]any)

	if v, ok := easyRide["rest_time_seconds"]; !ok || v != nil {
		t.Errorf("easy-ride rest_time_seconds = %v, %v; want a nil default", v, ok)
	}

	if !cmp.Equal(easyRide["previous_names"], []any{}) {
		t.Errorf("easy-ride previous_names = %#v, want an empty list", easyRide["previous_names"])
	}

	if !cmp.Equal(benchPress["previous_names"], []any{"Old Bench Press"}) {
		t.Errorf("bench-press previous_names = %#v", benchPress["previous_names"])
	}

	if _, ok := catalog.Equipment[0].(map[string]any)["rest_time_seconds"]; ok {
		t.Error("equipment got an exercise default")
	}
}

// Mirrors CatalogLoaderTest::it_expands_the_set_count_shorthand and
// ::it_keeps_explicit_sets_and_defaults_their_difficulty_to_null.
func TestLoadExpandsTheShorthandAndNormalisesExplicitSets(t *testing.T) {
	t.Parallel()

	catalog := load(t, validDir)
	easySpin := catalog.WorkoutTemplates[0].(map[string]any)["activities"].([]any)
	fullBody := catalog.WorkoutTemplates[1].(map[string]any)["activities"].([]any)

	want := []any{
		map[string]any{"exercise": "easy-ride", "sets": []any{map[string]any{"effort": 1800, "difficulty": 1}}},
		map[string]any{"exercise": "easy-ride", "sets": []any{
			map[string]any{"effort": 600, "difficulty": 2},
			map[string]any{"effort": 600, "difficulty": 2},
		}},
	}
	if diff := cmp.Diff(want, easySpin); diff != "" {
		t.Errorf("easy-spin (-want +got):\n%s", diff)
	}

	squat := map[string]any{"exercise": "squat", "sets": []any{
		map[string]any{"effort": 10, "difficulty": nil},
		map[string]any{"effort": 8, "difficulty": nil},
	}}
	if diff := cmp.Diff(squat, fullBody[1]); diff != "" {
		t.Errorf("squat (-want +got):\n%s", diff)
	}

	if sets := fullBody[0].(map[string]any)["sets"].([]any); len(sets) != 3 {
		t.Errorf("bench-press has %d sets, want 3", len(sets))
	}
}

// Mirrors CatalogLoaderTest::it_returns_a_retired_list_for_every_type (the
// validator treats a missing type as an empty list).
func TestLoadReadsRetiredRecords(t *testing.T) {
	t.Parallel()

	retired := load(t, validDir).Retired

	want := map[string]any{"equipment": []any{map[string]any{"slug": "old-rack", "names": []any{"Old Rack"}}}}
	if diff := cmp.Diff(want, retired); diff != "" {
		t.Errorf("retired (-want +got):\n%s", diff)
	}

	files := validFiles(t)
	delete(files, "retired.yaml")

	catalog, err := catalogfile.New(files).Load()
	if err != nil || len(catalog.Retired) != 0 {
		t.Errorf("without retired.yaml: %v, %v; want an empty map", catalog.Retired, err)
	}
}

// validFiles copies the valid fixture into memory.
func validFiles(t *testing.T) fstest.MapFS {
	t.Helper()

	files := fstest.MapFS{}

	for _, name := range []string{
		"equipment.yaml", "categories.yaml", "programs.yaml", "retired.yaml",
		"exercises/cardio.yaml", "exercises/strength.yaml",
		"workout-templates/cardio.yaml", "workout-templates/strength.yaml",
	} {
		data, err := os.ReadFile(validDir + "/" + name)
		if err != nil {
			t.Fatal(err)
		}

		files[name] = &fstest.MapFile{Data: data}
	}

	return files
}

// Mirrors CatalogLoaderTest::it_fails_when_the_directory_does_not_exist and
// ::it_fails_when_a_required_file_is_missing, plus the Go loader's own
// checks: unknown keys and malformed files.
func TestLoadRejectsBrokenFiles(t *testing.T) {
	t.Parallel()

	if _, err := catalogfile.New(os.DirFS(validDir + "/missing")).Load(); !errors.Is(err, catalogfile.ErrNoCatalog) {
		t.Errorf("missing directory: err = %v", err)
	}

	tests := []struct {
		name  string
		edit  func(fstest.MapFS)
		error string
	}{
		{"missing file", func(f fstest.MapFS) { delete(f, "programs.yaml") }, "catalog file programs.yaml does not exist"},
		{"empty directory", func(f fstest.MapFS) {
			delete(f, "exercises/cardio.yaml")
			delete(f, "exercises/strength.yaml")
		}, "catalog directory exercises has no data files"},
		{"not a list", func(f fstest.MapFS) { f["categories.yaml"].Data = []byte("slug: chest\n") }, "catalog file categories.yaml must hold a list of records"},
		{"syntax error", func(f fstest.MapFS) { f["categories.yaml"].Data = []byte("- [unclosed\n") }, "catalog file categories.yaml:"},
		{"unknown record key", func(f fstest.MapFS) {
			f["categories.yaml"].Data = []byte("- { slug: chest, colour: red }\n")
		}, `categories [chest]: unknown key "colour"`},
		{"unknown activity key", func(f fstest.MapFS) {
			f["workout-templates/strength.yaml"].Data = []byte("- { slug: x, activities: [{ exercise: squat, reps: 5 }] }\n")
		}, `workout_templates [x]: activity 1: unknown key "reps"`},
		{"unknown set key", func(f fstest.MapFS) {
			f["workout-templates/strength.yaml"].Data = []byte("- { activities: [{ exercise: squat, sets: [{ effort: 5, weight: 60 }] }] }\n")
		}, `workout_templates (no slug): activity 1: set 1: unknown key "weight"`},
		{"unknown schedule key", func(f fstest.MapFS) {
			f["programs.yaml"].Data = []byte("- { slug: p, schedule: [{ day: monday }] }\n")
		}, `programs [p]: schedule: unknown key "day"`},
		{"retired not a mapping", func(f fstest.MapFS) { f["retired.yaml"].Data = []byte("- old-rack\n") }, "retired.yaml must map record types to lists"},
		{"retired type not a list", func(f fstest.MapFS) { f["retired.yaml"].Data = []byte("equipment: old-rack\n") }, "retired.yaml: equipment must be a list"},
		{"unknown retired key", func(f fstest.MapFS) {
			f["retired.yaml"].Data = []byte("equipment: [{ slug: old-rack, reason: gone }]\n")
		}, `retired.yaml: equipment: unknown key "reason"`},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			files := validFiles(t)
			tt.edit(files)

			_, err := catalogfile.New(files).Load()
			if err == nil || !strings.Contains(err.Error(), tt.error) {
				t.Errorf("err = %v, want one containing %q", err, tt.error)
			}
		})
	}
}

// Records that are not mappings, and empty files, reach the validator.
func TestLoadLeavesWrongTypesToTheValidator(t *testing.T) {
	t.Parallel()

	files := validFiles(t)
	files["categories.yaml"].Data = append(files["categories.yaml"].Data, []byte("- just-a-string\n")...)
	files["workout-templates/strength.yaml"].Data = []byte("- { slug: x, activities: [squat, { exercise: squat, sets: [5] }] }\n")
	files["retired.yaml"].Data = []byte("")

	catalog, err := catalogfile.New(files).Load()
	if err != nil {
		t.Fatal(err)
	}

	if last := catalog.Categories[len(catalog.Categories)-1]; last != "just-a-string" {
		t.Errorf("last category = %#v", last)
	}

	activities := catalog.WorkoutTemplates[1].(map[string]any)["activities"].([]any)
	if activities[0] != "squat" || !cmp.Equal(activities[1].(map[string]any)["sets"], []any{5}) {
		t.Errorf("activities = %#v", activities)
	}

	if len(catalog.Retired) != 0 {
		t.Errorf("empty retired.yaml = %#v", catalog.Retired)
	}
}
