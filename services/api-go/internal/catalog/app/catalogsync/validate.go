package catalogsync

import (
	"fmt"
	"maps"
	"regexp"
	"slices"
	"strings"
	"unicode/utf8"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
)

// Rule identifies a validation rule. Each is one violation message of PHP's
// CatalogValidator; catalog/testdata/invalid/<rule>/ breaks exactly that rule.
type Rule string

// The rules, in the order the validator checks them.
const (
	RuleRecordNotMapping          Rule = "record-not-mapping"
	RuleSlugRequired              Rule = "slug-required"
	RuleSlugFormat                Rule = "slug-format"
	RuleSlugDuplicate             Rule = "slug-duplicate"
	RuleTranslationsRequired      Rule = "translations-required"
	RuleLocaleUnsupported         Rule = "locale-unsupported"
	RuleFieldNotTranslatable      Rule = "field-not-translatable"
	RuleFieldRequired             Rule = "field-required"
	RuleFieldTooLong              Rule = "field-too-long"
	RulePreviousNamesNotList      Rule = "previous-names-not-list"
	RulePreviousNamesBlank        Rule = "previous-names-blank"
	RuleNameDuplicate             Rule = "name-duplicate"
	RuleEquipmentUnitInvalid      Rule = "equipment-unit-invalid"
	RuleExerciseEquipmentUnknown  Rule = "exercise-equipment-unknown"
	RuleExerciseEffortTypeInvalid Rule = "exercise-effort-type-invalid"
	RuleExerciseRestTimeRange     Rule = "exercise-rest-time-range"
	RuleExerciseRestTimeRequired  Rule = "exercise-rest-time-required"
	RuleExerciseCategoriesMissing Rule = "exercise-categories-required"
	RuleExerciseCategoryUnknown   Rule = "exercise-category-unknown"
	RuleExerciseCategoryRepeated  Rule = "exercise-category-repeated"
	RuleEquipmentUnused           Rule = "equipment-unused"
	RuleCategoryUnused            Rule = "category-unused"
	RuleTemplateActivitiesMissing Rule = "template-activities-required"
	RuleActivityExerciseUnknown   Rule = "activity-exercise-unknown"
	RuleActivitySetsMissing       Rule = "activity-sets-required"
	RuleSetEffortInvalid          Rule = "set-effort-invalid"
	RuleSetDifficultyInvalid      Rule = "set-difficulty-invalid"
	RuleSetDifficultyWithoutUnit  Rule = "set-difficulty-without-unit"
	RuleSetDifficultyWeight       Rule = "set-difficulty-weight"
	RuleSetHeartRateZoneRange     Rule = "set-heart-rate-zone-range"
	RuleProgramScheduleMissing    Rule = "program-schedule-required"
	RuleScheduleWeekdayInvalid    Rule = "schedule-weekday-invalid"
	RuleScheduleTemplateUnknown   Rule = "schedule-template-unknown"
	RuleScheduleDuplicate         Rule = "schedule-duplicate"
	RuleRetiredTypeUnknown        Rule = "retired-type-unknown"
	RuleRetiredRecordNotMapping   Rule = "retired-record-not-mapping"
	RuleRetiredSlugActive         Rule = "retired-slug-active"
	RuleRetiredNamesMissing       Rule = "retired-names-required"
	RuleRetiredNamesBlank         Rule = "retired-names-blank"
	RuleRetiredNameActive         Rule = "retired-name-active"
)

// Rules lists every rule.
var Rules = []Rule{
	RuleRecordNotMapping, RuleSlugRequired, RuleSlugFormat, RuleSlugDuplicate, RuleTranslationsRequired,
	RuleLocaleUnsupported, RuleFieldNotTranslatable, RuleFieldRequired, RuleFieldTooLong, RulePreviousNamesNotList,
	RulePreviousNamesBlank, RuleNameDuplicate, RuleEquipmentUnitInvalid, RuleExerciseEquipmentUnknown,
	RuleExerciseEffortTypeInvalid, RuleExerciseRestTimeRange, RuleExerciseRestTimeRequired,
	RuleExerciseCategoriesMissing, RuleExerciseCategoryUnknown, RuleExerciseCategoryRepeated, RuleEquipmentUnused,
	RuleCategoryUnused, RuleTemplateActivitiesMissing, RuleActivityExerciseUnknown, RuleActivitySetsMissing,
	RuleSetEffortInvalid, RuleSetDifficultyInvalid, RuleSetDifficultyWithoutUnit, RuleSetDifficultyWeight,
	RuleSetHeartRateZoneRange, RuleProgramScheduleMissing, RuleScheduleWeekdayInvalid, RuleScheduleTemplateUnknown,
	RuleScheduleDuplicate, RuleRetiredTypeUnknown, RuleRetiredRecordNotMapping, RuleRetiredSlugActive,
	RuleRetiredNamesMissing, RuleRetiredNamesBlank, RuleRetiredNameActive,
}

// Violation is one broken rule, with PHP's message.
type Violation struct {
	Rule    Rule
	Message string
}

// String returns the message.
func (v Violation) String() string { return v.Message }

const (
	slugMaxLength       = 100
	maxRestTimeSeconds  = 3600
	nameMaxLength       = 255
	descriptionMaxLimit = 2000
)

var slugPattern = regexp.MustCompile(`^[a-z0-9]+(-[a-z0-9]+)*$`)

var fieldMaxLength = map[string]int{"name": nameMaxLength, "description": descriptionMaxLimit}

// translatableFields are the fields each kind translates, in order.
var translatableFields = map[ports.Kind][]string{
	ports.KindEquipment:        {"name"},
	ports.KindCategories:       {"name"},
	ports.KindExercises:        {"name", "description"},
	ports.KindWorkoutTemplates: {"name", "description"},
	ports.KindPrograms:         {"name", "description"},
}

var (
	difficultyUnits = []domain.DifficultyUnit{domain.Kilograms, domain.Pounds, domain.Plates, domain.HeartRateZone, domain.NoDifficulty}
	weightUnits     = []domain.DifficultyUnit{domain.Kilograms, domain.Pounds, domain.Plates}
	effortTypes     = []domain.EffortType{domain.Repetitions, domain.Duration}
	weekdayNames    = []domain.Weekday{domain.Monday, domain.Tuesday, domain.Wednesday, domain.Thursday, domain.Friday, domain.Saturday, domain.Sunday}
)

// validator is PHP's CatalogValidator, check for check and in the same order.
type validator struct {
	locales    []string
	violations []Violation
}

// Validate checks the catalog without touching the database. locales are the
// supported locales; every one needs every translatable field.
func Validate(catalog ports.RawCatalog, locales []string) []Violation {
	v := &validator{locales: locales}

	records := recordsByKind(catalog)
	for _, kind := range ports.Kinds {
		v.commonFields(kind, records[kind])
	}

	equipmentUnits := v.equipment(catalog.Equipment)
	exerciseUnits := v.exercises(catalog.Exercises, equipmentUnits, slugs(catalog.Categories))

	v.usage(catalog)
	v.workoutTemplates(catalog.WorkoutTemplates, exerciseUnits)
	v.programs(catalog.Programs, slugs(catalog.WorkoutTemplates))
	v.retired(catalog, records)

	return v.violations
}

func recordsByKind(catalog ports.RawCatalog) map[ports.Kind][]any {
	return map[ports.Kind][]any{
		ports.KindEquipment: catalog.Equipment, ports.KindCategories: catalog.Categories,
		ports.KindExercises: catalog.Exercises, ports.KindWorkoutTemplates: catalog.WorkoutTemplates,
		ports.KindPrograms: catalog.Programs,
	}
}

func (v *validator) add(rule Rule, format string, args ...any) {
	v.violations = append(v.violations, Violation{Rule: rule, Message: fmt.Sprintf(format, args...)})
}

func (v *validator) commonFields(kind ports.Kind, records []any) {
	seenSlugs := map[string]bool{}
	seenNames := map[string]string{}

	for index, value := range records {
		label := label(string(kind), value, index)

		record, ok := value.(map[string]any)
		if !ok {
			v.add(RuleRecordNotMapping, "%s: record must be a mapping.", label)

			continue
		}

		v.slug(label, record["slug"], seenSlugs)
		v.translations(kind, label, record["translations"])
		v.previousNames(label, record["previous_names"])

		for _, ln := range namesByLocale(record) {
			key := ln.locale + "|" + normaliseName(ln.name)

			if owner, ok := seenNames[key]; ok && owner != label {
				v.add(RuleNameDuplicate, "%s: name %q (%s) is already used by %s.", label, ln.name, ln.locale, owner)

				continue
			}

			seenNames[key] = label
		}
	}
}

func (v *validator) slug(label string, value any, seen map[string]bool) {
	slug, ok := value.(string)
	if !ok || slug == "" {
		v.add(RuleSlugRequired, "%s: slug is required.", label)

		return
	}

	if len(slug) > slugMaxLength || !slugPattern.MatchString(slug) {
		v.add(RuleSlugFormat, "%s: slug must be lowercase words separated by hyphens, up to %d characters.", label, slugMaxLength)
	}

	if seen[slug] {
		v.add(RuleSlugDuplicate, "%s: slug is used more than once.", label)
	}

	seen[slug] = true
}

func (v *validator) translations(kind ports.Kind, label string, value any) {
	translations, ok := value.(map[string]any)
	if !ok {
		v.add(RuleTranslationsRequired, "%s: translations are required.", label)

		return
	}

	fields := translatableFields[kind]

	for _, locale := range slices.Sorted(maps.Keys(translations)) {
		if !slices.Contains(v.locales, locale) {
			v.add(RuleLocaleUnsupported, "%s: locale %q is not supported.", label, locale)
		}
	}

	for _, locale := range v.locales {
		values, _ := translations[locale].(map[string]any)

		for _, field := range slices.Sorted(maps.Keys(values)) {
			if !slices.Contains(fields, field) {
				v.add(RuleFieldNotTranslatable, "%s: field %q (%s) is not translatable.", label, field, locale)
			}
		}

		for _, field := range fields {
			text, ok := values[field].(string)
			if !ok || strings.TrimSpace(text) == "" {
				v.add(RuleFieldRequired, "%s: %s (%s) is required.", label, field, locale)

				continue
			}

			if utf8.RuneCountInString(text) > fieldMaxLength[field] {
				v.add(RuleFieldTooLong, "%s: %s (%s) is longer than %d characters.", label, field, locale, fieldMaxLength[field])
			}
		}
	}
}

func (v *validator) previousNames(label string, value any) {
	names, ok := value.([]any)
	if !ok {
		v.add(RulePreviousNamesNotList, "%s: previous_names must be a list.", label)

		return
	}

	for _, name := range names {
		if s, ok := name.(string); !ok || strings.TrimSpace(s) == "" {
			v.add(RulePreviousNamesBlank, "%s: previous_names must contain non-empty strings.", label)
		}
	}
}

// equipment returns the difficulty unit by equipment slug.
func (v *validator) equipment(records []any) map[string]domain.DifficultyUnit {
	units := map[string]domain.DifficultyUnit{}

	for index, value := range records {
		record, ok := value.(map[string]any)
		if !ok {
			continue
		}

		unit, ok := record["difficulty_unit"].(string)
		if !ok || !slices.Contains(difficultyUnits, domain.DifficultyUnit(unit)) {
			v.add(RuleEquipmentUnitInvalid, "%s: difficulty_unit must be one of %s.", label("equipment", record, index), join(difficultyUnits))

			continue
		}

		if slug, ok := record["slug"].(string); ok {
			units[slug] = domain.DifficultyUnit(unit)
		}
	}

	return units
}

// exercises returns the difficulty unit by exercise slug ("" when the
// equipment does not exist).
func (v *validator) exercises(records []any, equipmentUnits map[string]domain.DifficultyUnit, categorySlugs []string) map[string]domain.DifficultyUnit {
	units := map[string]domain.DifficultyUnit{}

	for index, value := range records {
		record, ok := value.(map[string]any)
		if !ok {
			continue
		}

		label := label("exercises", record, index)
		equipment, isString := record["equipment"].(string)

		if _, known := equipmentUnits[equipment]; !isString || !known {
			v.add(RuleExerciseEquipmentUnknown, "%s: equipment %q does not exist.", label, printable(record["equipment"]))
		}

		effortType, _ := record["effort_type"].(string)
		if !slices.Contains(effortTypes, domain.EffortType(effortType)) {
			v.add(RuleExerciseEffortTypeInvalid, "%s: effort_type must be one of %s.", label, join(effortTypes))
		}

		restTime := record["rest_time_seconds"]
		if restTime != nil {
			if seconds, ok := restTime.(int); !ok || seconds < 0 || seconds > maxRestTimeSeconds {
				v.add(RuleExerciseRestTimeRange, "%s: rest_time_seconds must be null or an integer from 0 to %d.", label, maxRestTimeSeconds)
			}
		}

		if restTime == nil && domain.EffortType(effortType) == domain.Repetitions {
			v.add(RuleExerciseRestTimeRequired, "%s: rest_time_seconds is required for a repetition-based exercise.", label)
		}

		v.categories(label, record["categories"], categorySlugs)

		if slug, ok := record["slug"].(string); ok {
			units[slug] = equipmentUnits[equipment]
		}
	}

	return units
}

func (v *validator) categories(label string, value any, categorySlugs []string) {
	categories, ok := value.([]any)
	if !ok || len(categories) == 0 {
		v.add(RuleExerciseCategoriesMissing, "%s: at least one category is required.", label)

		return
	}

	seen := map[any]bool{}
	repeated := false

	for _, category := range categories {
		if s, ok := category.(string); !ok || !slices.Contains(categorySlugs, s) {
			v.add(RuleExerciseCategoryUnknown, "%s: category %q does not exist.", label, printable(category))
		}

		if isComparable(category) {
			repeated = repeated || seen[category]
			seen[category] = true
		}
	}

	if repeated {
		v.add(RuleExerciseCategoryRepeated, "%s: categories must not repeat.", label)
	}
}

// usage requires every equipment item and category to be used by an exercise.
func (v *validator) usage(catalog ports.RawCatalog) {
	usedEquipment := map[string]bool{}
	usedCategories := map[string]bool{}

	for _, value := range catalog.Exercises {
		exercise, ok := value.(map[string]any)
		if !ok {
			continue
		}

		if s, ok := exercise["equipment"].(string); ok {
			usedEquipment[s] = true
		}

		categories, _ := exercise["categories"].([]any)
		for _, category := range categories {
			if s, ok := category.(string); ok {
				usedCategories[s] = true
			}
		}
	}

	for _, slug := range slugs(catalog.Equipment) {
		if !usedEquipment[slug] {
			v.add(RuleEquipmentUnused, "equipment [%s]: no exercise uses it.", slug)
		}
	}

	for _, slug := range slugs(catalog.Categories) {
		if !usedCategories[slug] {
			v.add(RuleCategoryUnused, "categories [%s]: no exercise belongs to it.", slug)
		}
	}
}

func (v *validator) workoutTemplates(records []any, exerciseUnits map[string]domain.DifficultyUnit) {
	for index, value := range records {
		record, ok := value.(map[string]any)
		if !ok {
			continue
		}

		label := label("workout_templates", record, index)

		activities, ok := record["activities"].([]any)
		if !ok || len(activities) == 0 {
			v.add(RuleTemplateActivitiesMissing, "%s: at least one activity is required.", label)

			continue
		}

		for position, value := range activities {
			activityLabel := fmt.Sprintf("%s activity %d", label, position+1)
			activity, _ := value.(map[string]any)
			exercise, isString := activity["exercise"].(string)

			unit, known := exerciseUnits[exercise]
			if !isString || !known {
				v.add(RuleActivityExerciseUnknown, "%s: exercise %q does not exist.", activityLabel, printable(activity["exercise"]))

				continue
			}

			v.sets(activityLabel, activity["sets"], unit)
		}
	}
}

func (v *validator) sets(label string, value any, unit domain.DifficultyUnit) {
	sets, ok := value.([]any)
	if !ok || len(sets) == 0 {
		v.add(RuleActivitySetsMissing, "%s: at least one set is required.", label)

		return
	}

	for position, value := range sets {
		setLabel := fmt.Sprintf("%s set %d", label, position+1)
		set, _ := value.(map[string]any)

		if effort, ok := set["effort"].(int); !ok || effort < 1 {
			v.add(RuleSetEffortInvalid, "%s: effort must be a positive integer.", setLabel)
		}

		difficulty := set["difficulty"]
		if difficulty == nil {
			continue
		}

		number, isNumber := toFloat(difficulty)
		if !isNumber || number < 0 {
			v.add(RuleSetDifficultyInvalid, "%s: difficulty must be null or a non-negative number.", setLabel)

			continue
		}

		if unit == domain.NoDifficulty {
			v.add(RuleSetDifficultyWithoutUnit, "%s: difficulty must be null because the equipment has no difficulty unit.", setLabel)
		}

		if slices.Contains(weightUnits, unit) {
			v.add(RuleSetDifficultyWeight, "%s: difficulty must be null because templates do not prescribe a weight.", setLabel)
		}

		if zone, isInt := difficulty.(int); unit == domain.HeartRateZone && (!isInt || zone < 1 || zone > 5) {
			v.add(RuleSetHeartRateZoneRange, "%s: heart-rate zone must be an integer from 1 to 5.", setLabel)
		}
	}
}

func (v *validator) programs(records []any, templateSlugs []string) {
	for index, value := range records {
		record, ok := value.(map[string]any)
		if !ok {
			continue
		}

		label := label("programs", record, index)

		schedule, ok := record["schedule"].([]any)
		if !ok || len(schedule) == 0 {
			v.add(RuleProgramScheduleMissing, "%s: at least one scheduled workout is required.", label)

			continue
		}

		seen := map[string]bool{}

		for position, value := range schedule {
			entryLabel := fmt.Sprintf("%s schedule entry %d", label, position+1)
			entry, _ := value.(map[string]any)
			weekday, _ := entry["weekday"].(string)
			validWeekday := domain.Weekday(weekday).Valid()

			if !validWeekday {
				v.add(RuleScheduleWeekdayInvalid, "%s: weekday must be one of %s.", entryLabel, join(weekdayNames))
			}

			template, isString := entry["template"].(string)
			if !isString || !slices.Contains(templateSlugs, template) {
				v.add(RuleScheduleTemplateUnknown, "%s: workout template %q does not exist.", entryLabel, printable(entry["template"]))

				continue
			}

			if validWeekday {
				key := weekday + "|" + template
				if seen[key] {
					v.add(RuleScheduleDuplicate, "%s: workout template %q is already scheduled on %s.", entryLabel, template, weekday)
				}

				seen[key] = true
			}
		}
	}
}

func (v *validator) retired(catalog ports.RawCatalog, records map[ports.Kind][]any) {
	for _, kind := range slices.Sorted(maps.Keys(catalog.Retired)) {
		if !slices.Contains(ports.Kinds, ports.Kind(kind)) {
			v.add(RuleRetiredTypeUnknown, "retired: %q is not a catalog type.", kind)
		}
	}

	for _, kind := range ports.Kinds {
		activeSlugs := slugs(records[kind])
		activeNames := activeEnglishNames(records[kind])
		seenSlugs := map[string]bool{}

		entries, _ := catalog.Retired[string(kind)].([]any)
		for index, value := range entries {
			label := label("retired "+string(kind), value, index)

			entry, ok := value.(map[string]any)
			if !ok {
				v.add(RuleRetiredRecordNotMapping, "%s: record must be a mapping.", label)

				continue
			}

			v.slug(label, entry["slug"], seenSlugs)

			if slug, ok := entry["slug"].(string); ok && slices.Contains(activeSlugs, slug) {
				v.add(RuleRetiredSlugActive, "%s: slug belongs to an active record.", label)
			}

			names, ok := entry["names"].([]any)
			if !ok || len(names) == 0 {
				v.add(RuleRetiredNamesMissing, "%s: names must list at least one English name.", label)

				continue
			}

			for _, value := range names {
				name, ok := value.(string)
				if !ok || strings.TrimSpace(name) == "" {
					v.add(RuleRetiredNamesBlank, "%s: names must contain non-empty strings.", label)

					continue
				}

				if activeNames[normaliseName(name)] {
					v.add(RuleRetiredNameActive, "%s: name %q belongs to an active record.", label, name)
				}
			}
		}
	}
}

type localeName struct{ locale, name string }

// namesByLocale returns the names a record claims, per locale in sorted
// order and without repeats; previous names are English.
func namesByLocale(record map[string]any) []localeName {
	byLocale := map[string][]string{}

	translations, _ := record["translations"].(map[string]any)
	for locale, value := range translations {
		fields, _ := value.(map[string]any)
		if name, ok := fields["name"].(string); ok && strings.TrimSpace(name) != "" {
			byLocale[locale] = append(byLocale[locale], name)
		}
	}

	previous, _ := record["previous_names"].([]any)
	for _, value := range previous {
		if name, ok := value.(string); ok && strings.TrimSpace(name) != "" {
			byLocale["en"] = append(byLocale["en"], name)
		}
	}

	var names []localeName

	for _, locale := range slices.Sorted(maps.Keys(byLocale)) {
		seen := map[string]bool{}

		for _, name := range byLocale[locale] {
			if !seen[name] {
				seen[name] = true
				names = append(names, localeName{locale, name})
			}
		}
	}

	return names
}

func activeEnglishNames(records []any) map[string]bool {
	names := map[string]bool{}

	for _, value := range records {
		if record, ok := value.(map[string]any); ok {
			for _, ln := range namesByLocale(record) {
				if ln.locale == "en" {
					names[normaliseName(ln.name)] = true
				}
			}
		}
	}

	return names
}

func slugs(records []any) []string {
	var out []string

	for _, value := range records {
		if record, ok := value.(map[string]any); ok {
			if slug, ok := record["slug"].(string); ok {
				out = append(out, slug)
			}
		}
	}

	return out
}

// label names a record in a message: its slug, or its position.
func label(kind string, value any, index int) string {
	if record, ok := value.(map[string]any); ok {
		if slug, ok := record["slug"].(string); ok && slug != "" {
			return kind + " [" + slug + "]"
		}
	}

	return fmt.Sprintf("%s #%d", kind, index)
}

// normaliseName is how names are compared: trimmed and lowercased.
func normaliseName(name string) string {
	return strings.ToLower(strings.TrimSpace(name))
}

func printable(value any) string {
	switch v := value.(type) {
	case nil:
		return "NULL"
	case string, int, float64, bool:
		return fmt.Sprint(v)
	default:
		return fmt.Sprintf("%T", v)
	}
}

func toFloat(value any) (float64, bool) {
	switch v := value.(type) {
	case int:
		return float64(v), true
	case float64:
		return v, true
	default:
		return 0, false
	}
}

func isComparable(value any) bool {
	switch value.(type) {
	case string, int, float64, bool, nil:
		return true
	default:
		return false
	}
}

func join[T ~string](values []T) string {
	out := make([]string, len(values))
	for i, v := range values {
		out[i] = string(v)
	}

	return strings.Join(out, ", ")
}
