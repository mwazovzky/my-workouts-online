package domain_test

import (
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

func TestLocalizedTextResolve(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name   string
		text   domain.LocalizedText
		locale i18n.Locale
		want   string
	}{
		{"translation present", domain.LocalizedText{Source: "Chest", Other: map[i18n.Locale]string{i18n.RU: "Грудь"}}, i18n.RU, "Грудь"},
		{"source locale", domain.LocalizedText{Source: "Chest", Other: map[i18n.Locale]string{i18n.RU: "Грудь"}}, i18n.EN, "Chest"},
		{"blank translation falls back", domain.LocalizedText{Source: "Chest", Other: map[i18n.Locale]string{i18n.RU: ""}}, i18n.RU, "Chest"},
		{"missing translation falls back", domain.LocalizedText{Source: "Chest"}, i18n.RU, "Chest"},
		{"unknown locale falls back", domain.LocalizedText{Source: "Chest", Other: map[i18n.Locale]string{i18n.RU: "Грудь"}}, "de", "Chest"},
		{"translation without source", domain.LocalizedText{Other: map[i18n.Locale]string{i18n.RU: "Описание"}}, i18n.RU, "Описание"},
		{"no text at all", domain.LocalizedText{}, i18n.RU, ""},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			if got := tt.text.Resolve(tt.locale); got != tt.want {
				t.Errorf("Resolve(%q) = %q, want %q", tt.locale, got, tt.want)
			}
		})
	}
}

func TestWeekdayValid(t *testing.T) {
	t.Parallel()

	for _, w := range []domain.Weekday{domain.Monday, domain.Tuesday, domain.Wednesday, domain.Thursday, domain.Friday, domain.Saturday, domain.Sunday} {
		if !w.Valid() {
			t.Errorf("%s is not valid", w)
		}
	}

	for _, w := range []domain.Weekday{"", "Monday", "mon"} {
		if w.Valid() {
			t.Errorf("%q is valid", w)
		}
	}
}

func TestExerciseIsRetired(t *testing.T) {
	t.Parallel()

	now := time.Now()
	if (domain.Exercise{}).IsRetired() || !(domain.Exercise{RetiredAt: &now}).IsRetired() {
		t.Error("IsRetired does not follow RetiredAt")
	}
}
