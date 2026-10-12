package catalogfile_test

import (
	"io/fs"
	"maps"
	"os"
	"slices"
	"testing"
	"testing/fstest"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/catalogfile"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/catalogsync"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
)

var locales = []string{"en", "ru"}

const invalidDir = shippedDir + "/testdata/invalid"

// consequences are the rules a fixture also breaks because its rule makes
// a reference dangle, as in PHP: equipment with an invalid unit has no unit,
// so its exercise's equipment counts as unknown.
var consequences = map[catalogsync.Rule][]catalogsync.Rule{
	catalogsync.RuleEquipmentUnitInvalid: {catalogsync.RuleExerciseEquipmentUnknown},
}

func rulesOf(violations []catalogsync.Violation) []catalogsync.Rule {
	var rules []catalogsync.Rule
	for _, v := range violations {
		if !slices.Contains(rules, v.Rule) {
			rules = append(rules, v.Rule)
		}
	}

	return rules
}

// Mirrors CatalogValidatorTest::a_valid_catalog_has_no_violations.
func TestTheValidFixtureHasNoViolations(t *testing.T) {
	t.Parallel()

	if violations := catalogsync.Validate(load(t, validDir), locales); len(violations) > 0 {
		t.Errorf("violations: %v", violations)
	}
}

// Every rule has catalog/testdata/invalid/<rule>/, and every fixture there
// is a rule.
func TestEveryRuleHasAFixture(t *testing.T) {
	t.Parallel()

	entries, err := os.ReadDir(invalidDir)
	if err != nil {
		t.Fatal(err)
	}

	var dirs []catalogsync.Rule
	for _, e := range entries {
		dirs = append(dirs, catalogsync.Rule(e.Name()))
	}

	rules := slices.Sorted(slices.Values(catalogsync.Rules))
	if !slices.Equal(dirs, rules) {
		t.Errorf("fixtures %v\nrules    %v", dirs, rules)
	}
}

// Each fixture is the valid catalog with its files replaced by the ones in
// its directory; it must break its rule and no other (apart from the
// consequences above). Mirrors CatalogValidatorTest's rule tests.
func TestEachFixtureBreaksItsRule(t *testing.T) {
	t.Parallel()

	for _, rule := range catalogsync.Rules {
		t.Run(string(rule), func(t *testing.T) {
			t.Parallel()

			files := validFiles(t)
			overlay := os.DirFS(invalidDir + "/" + string(rule))

			if err := fs.WalkDir(overlay, ".", func(path string, d fs.DirEntry, err error) error {
				if err != nil || d.IsDir() {
					return err
				}

				data, err := fs.ReadFile(overlay, path)
				files[path] = &fstest.MapFile{Data: data}

				return err
			}); err != nil {
				t.Fatal(err)
			}

			catalog, err := catalogfile.New(files).Load()
			if err != nil {
				t.Fatal(err)
			}

			violations := catalogsync.Validate(catalog, locales)
			want := append([]catalogsync.Rule{rule}, consequences[rule]...)

			if got := rulesOf(violations); !slices.Equal(slices.Sorted(slices.Values(got)), slices.Sorted(slices.Values(want))) {
				t.Errorf("rules %v, want %v; violations:\n%v", got, want, violations)
			}
		})
	}
}

// Mirrors CatalogDataIntegrityTest::the_shipped_catalog_is_valid and
// ::the_shipped_catalog_defines_every_record_type.
func TestTheShippedCatalogIsValidAndComplete(t *testing.T) {
	t.Parallel()

	catalog := load(t, shippedDir)

	if violations := catalogsync.Validate(catalog, locales); len(violations) > 0 {
		t.Errorf("violations: %v", violations)
	}

	counts := map[string]int{
		"equipment": len(catalog.Equipment), "categories": len(catalog.Categories), "exercises": len(catalog.Exercises),
		"workout_templates": len(catalog.WorkoutTemplates), "programs": len(catalog.Programs),
	}
	for _, kind := range slices.Sorted(maps.Keys(counts)) {
		if counts[kind] == 0 {
			t.Errorf("the shipped catalog defines no %s", kind)
		}
	}
}

// Mirrors CatalogValidatorTest::it_reports_every_violation_at_once, and
// pins the messages, which are PHP's apart from the enum lists.
func TestViolationMessages(t *testing.T) {
	t.Parallel()

	files := validFiles(t)
	files["equipment.yaml"].Data = []byte("- { difficulty_unit: grams, translations: { en: { name: Rack }, ru: { name: Стойка } } }\n" +
		string(files["equipment.yaml"].Data))
	files["programs.yaml"].Data = []byte("- { slug: p, translations: { en: { name: P, description: D }, ru: { name: П, description: Д } }, " +
		"schedule: [{ weekday: Monday, template: easy-spin }, { template: 7 }] }\n")

	got := catalogsync.Validate(must(t, files), locales)

	want := []string{
		"equipment #0: slug is required.",
		"equipment #0: difficulty_unit must be one of kilograms, pounds, plates, heart_rate_zone, none.",
		"programs [p] schedule entry 1: weekday must be one of monday, tuesday, wednesday, thursday, friday, saturday, sunday.",
		"programs [p] schedule entry 2: weekday must be one of monday, tuesday, wednesday, thursday, friday, saturday, sunday.",
		`programs [p] schedule entry 2: workout template "7" does not exist.`,
	}

	var messages []string
	for _, v := range got {
		messages = append(messages, v.String())
	}

	if !slices.Equal(messages, want) {
		t.Errorf("messages:\n%q\nwant:\n%q", messages, want)
	}
}

func must(t *testing.T, files fstest.MapFS) ports.RawCatalog {
	t.Helper()

	catalog, err := catalogfile.New(files).Load()
	if err != nil {
		t.Fatal(err)
	}

	return catalog
}
