package catalogsync

import (
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

// definition is a validated catalog with its types settled. Only Validate's
// silence makes the conversions below safe.
type definition struct {
	equipment  []equipmentDef
	categories []categoryDef
	exercises  []exerciseDef
	templates  []templateDef
	programs   []programDef
	retired    map[ports.Kind][]retiredDef
}

// texts are a record's translations: locale, then field.
type texts map[string]map[string]string

type common struct {
	slug          string
	translations  texts
	previousNames []string
}

type equipmentDef struct {
	common
	unit domain.DifficultyUnit
}

type categoryDef struct{ common }

type exerciseDef struct {
	common
	equipment       string
	effortType      domain.EffortType
	restTimeSeconds *int
	categories      []string
}

type templateDef struct {
	common
	activities []activityDef
}

type activityDef struct {
	exercise string
	sets     []setDef
}

type setDef struct {
	effort     int
	difficulty *float64
}

type programDef struct {
	common
	schedule []scheduleDef
}

type scheduleDef struct {
	weekday  domain.Weekday
	template string
}

type retiredDef struct {
	slug  string
	names []string
}

func build(raw ports.RawCatalog) definition {
	d := definition{retired: map[ports.Kind][]retiredDef{}}

	for _, value := range raw.Equipment {
		r := value.(map[string]any)
		d.equipment = append(d.equipment, equipmentDef{common: commonOf(r), unit: domain.DifficultyUnit(r["difficulty_unit"].(string))})
	}

	for _, value := range raw.Categories {
		d.categories = append(d.categories, categoryDef{commonOf(value.(map[string]any))})
	}

	for _, value := range raw.Exercises {
		r := value.(map[string]any)

		var rest *int
		if seconds, ok := r["rest_time_seconds"].(int); ok {
			rest = &seconds
		}

		d.exercises = append(d.exercises, exerciseDef{
			common: commonOf(r), equipment: r["equipment"].(string), effortType: domain.EffortType(r["effort_type"].(string)),
			restTimeSeconds: rest, categories: stringList(r["categories"]),
		})
	}

	for _, value := range raw.WorkoutTemplates {
		r := value.(map[string]any)
		template := templateDef{common: commonOf(r)}

		for _, a := range r["activities"].([]any) {
			activity := a.(map[string]any)
			def := activityDef{exercise: activity["exercise"].(string)}

			for _, s := range activity["sets"].([]any) {
				set := s.(map[string]any)

				var difficulty *float64
				if number, ok := toFloat(set["difficulty"]); ok {
					difficulty = &number
				}

				def.sets = append(def.sets, setDef{effort: set["effort"].(int), difficulty: difficulty})
			}

			template.activities = append(template.activities, def)
		}

		d.templates = append(d.templates, template)
	}

	for _, value := range raw.Programs {
		r := value.(map[string]any)
		program := programDef{common: commonOf(r)}

		for _, e := range r["schedule"].([]any) {
			entry := e.(map[string]any)
			program.schedule = append(program.schedule, scheduleDef{
				weekday: domain.Weekday(entry["weekday"].(string)), template: entry["template"].(string),
			})
		}

		d.programs = append(d.programs, program)
	}

	for _, kind := range ports.Kinds {
		entries, _ := raw.Retired[string(kind)].([]any)
		for _, e := range entries {
			entry := e.(map[string]any)
			d.retired[kind] = append(d.retired[kind], retiredDef{slug: entry["slug"].(string), names: stringList(entry["names"])})
		}
	}

	return d
}

func commonOf(r map[string]any) common {
	c := common{slug: r["slug"].(string), translations: texts{}, previousNames: stringList(r["previous_names"])}

	for locale, value := range r["translations"].(map[string]any) {
		c.translations[locale] = map[string]string{}
		for field, text := range value.(map[string]any) {
			c.translations[locale][field] = text.(string)
		}
	}

	return c
}

func stringList(value any) []string {
	list, _ := value.([]any)
	out := make([]string, len(list))

	for i, v := range list {
		out[i] = v.(string)
	}

	return out
}

// englishNames are the names an unmanaged row is adopted by.
func (c common) englishNames() []string {
	return append([]string{c.translations[string(i18n.EN)]["name"]}, c.previousNames...)
}

// text is one translatable field as stored: English in the column, the rest
// in translations.
func (c common) text(field string) domain.LocalizedText {
	t := domain.LocalizedText{}

	for locale, fields := range c.translations {
		value, ok := fields[field]
		if !ok {
			continue
		}

		if locale == string(i18n.EN) {
			t.Source = value

			continue
		}

		if t.Other == nil {
			t.Other = map[i18n.Locale]string{}
		}

		t.Other[i18n.Locale(locale)] = value
	}

	return t
}
