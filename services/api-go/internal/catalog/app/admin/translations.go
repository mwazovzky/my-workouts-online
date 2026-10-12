package admin

import (
	"maps"
	"strings"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

// Text is one locale's submitted values; nil means blank.
type Text struct {
	Name        *string
	Description *string
}

// Translations are a submitted `translations` object: English always (the
// contract requires it) and the other locales that were sent. A field left
// out is blank, as the admin forms always send every field.
type Translations struct {
	EN    Text
	Other map[i18n.Locale]Text
}

// Texts are a record's name and description as stored.
type Texts struct {
	Name        domain.LocalizedText
	Description domain.LocalizedText
}

// apply merges submitted translations into the stored ones, as PHP's
// updateTranslations does after its TrimStrings middleware: English goes
// to the columns (a blank description clears it), a locale that was sent
// sets each non-blank value and removes each blank one, a locale that was
// not sent is left alone. A blank English name is a 422 at
// /translations/en/name. withDescription is false for equipment and
// categories, which have only a name.
func (t Translations) apply(v *validate.Validator, current Texts, withDescription bool) Texts {
	out := Texts{Name: copyText(current.Name), Description: copyText(current.Description)}

	name := trimmed(t.EN.Name)
	v.Object("translations", func(v *validate.Validator) {
		v.Object("en", func(v *validate.Validator) {
			v.Check(name != "", "name", apperr.FieldRequired, "An English name is required.", map[string]string{"attribute": "translations.en.name"})
		})
	})

	out.Name.Source = name
	if withDescription {
		out.Description.Source = trimmed(t.EN.Description)
	}

	for locale, text := range t.Other {
		set(&out.Name, locale, trimmed(text.Name))

		if withDescription {
			set(&out.Description, locale, trimmed(text.Description))
		}
	}

	return out
}

func set(t *domain.LocalizedText, locale i18n.Locale, value string) {
	if value == "" {
		delete(t.Other, locale)

		return
	}

	if t.Other == nil {
		t.Other = map[i18n.Locale]string{}
	}

	t.Other[locale] = value
}

func copyText(t domain.LocalizedText) domain.LocalizedText {
	return domain.LocalizedText{Source: t.Source, Other: maps.Clone(t.Other)}
}

func trimmed(s *string) string {
	if s == nil {
		return ""
	}

	return strings.TrimSpace(*s)
}
