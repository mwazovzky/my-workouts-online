// Package postgres is the catalog's Postgres adapter: the Programs,
// Templates and Exercises repositories over the sqlc queries in sql/catalog
// (generated into sqlcgen). Rows carry their raw translations jsonb, which
// becomes domain.LocalizedText here; the use cases pick the locale. Related
// rows are read with one query per relation, never one per row.
package postgres

import (
	"encoding/json"
	"fmt"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

// translations is the translations column, shaped {"ru": {"name": …}}
// (ADR 0004).
type translations map[i18n.Locale]map[string]string

func parseTranslations(raw []byte) (translations, error) {
	var t translations
	if len(raw) == 0 {
		return t, nil
	}

	if err := json.Unmarshal(raw, &t); err != nil {
		return nil, fmt.Errorf("catalog: decode translations: %w", err)
	}

	return t, nil
}

// text returns field as a LocalizedText over source.
func (t translations) text(field string, source *string) domain.LocalizedText {
	text := domain.LocalizedText{}
	if source != nil {
		text.Source = *source
	}

	for locale, fields := range t {
		if value := fields[field]; value != "" {
			if text.Other == nil {
				text.Other = map[i18n.Locale]string{}
			}

			text.Other[locale] = value
		}
	}

	return text
}

// named returns a row's name and description.
func named(name string, description *string, raw []byte) (domain.LocalizedText, domain.LocalizedText, error) {
	t, err := parseTranslations(raw)
	if err != nil {
		return domain.LocalizedText{}, domain.LocalizedText{}, err
	}

	return t.text("name", &name), t.text("description", description), nil
}

// name returns a row's name.
func name(source string, raw []byte) (domain.LocalizedText, error) {
	t, err := parseTranslations(raw)
	if err != nil {
		return domain.LocalizedText{}, err
	}

	return t.text("name", &source), nil
}
