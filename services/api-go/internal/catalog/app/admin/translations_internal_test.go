package admin

import (
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

func ptr(s string) *string { return &s }

var stored = Texts{
	Name:        domain.LocalizedText{Source: "Bench Press", Other: map[i18n.Locale]string{i18n.RU: "Жим лёжа"}},
	Description: domain.LocalizedText{Source: "Press it.", Other: map[i18n.Locale]string{i18n.RU: "Жмите."}},
}

func TestApplyTranslations(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name string
		in   Translations
		want Texts
	}{
		{
			"values are trimmed and replace the stored ones",
			Translations{EN: Text{Name: ptr("  Floor Press "), Description: ptr("Lie down.")}, Other: map[i18n.Locale]Text{i18n.RU: {Name: ptr(" Жим с пола "), Description: ptr("Лёжа.")}}},
			Texts{
				Name:        domain.LocalizedText{Source: "Floor Press", Other: map[i18n.Locale]string{i18n.RU: "Жим с пола"}},
				Description: domain.LocalizedText{Source: "Lie down.", Other: map[i18n.Locale]string{i18n.RU: "Лёжа."}},
			},
		},
		{
			"blank values remove a locale's translation and clear the English description",
			Translations{EN: Text{Name: ptr("Bench Press")}, Other: map[i18n.Locale]Text{i18n.RU: {Name: ptr("  "), Description: nil}}},
			Texts{Name: domain.LocalizedText{Source: "Bench Press", Other: map[i18n.Locale]string{}}, Description: domain.LocalizedText{Other: map[i18n.Locale]string{}}},
		},
		{
			"a locale that was not sent is left alone",
			Translations{EN: Text{Name: ptr("Bench Press"), Description: ptr("Press it.")}},
			stored,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			v := validate.New()
			got := tt.in.apply(v, stored, true)

			if err := v.Err(); err != nil {
				t.Fatal(err)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Errorf("texts (-want +got):\n%s", diff)
			}
		})
	}

	if stored.Name.Other[i18n.RU] != "Жим лёжа" {
		t.Error("apply changed the stored texts")
	}
}

func TestApplyRequiresAnEnglishName(t *testing.T) {
	t.Parallel()

	for _, name := range []*string{nil, ptr(""), ptr("   ")} {
		v := validate.New()
		(Translations{EN: Text{Name: name}}).apply(v, Texts{}, false)

		appErr, ok := apperr.As(v.Err())
		if !ok || len(appErr.Fields) != 1 || appErr.Fields[0].Pointer != "/translations/en/name" ||
			appErr.Fields[0].Code != apperr.FieldRequired || appErr.Fields[0].Detail != "An English name is required." {
			t.Errorf("name %v: err = %v", name, v.Err())
		}
	}
}

func TestApplyWithoutDescriptionIgnoresIt(t *testing.T) {
	t.Parallel()

	v := validate.New()
	got := (Translations{EN: Text{Name: ptr("Mat"), Description: ptr("ignored")}}).apply(v, Texts{}, false)

	if got.Description.Source != "" || got.Name.Source != "Mat" {
		t.Errorf("texts = %+v", got)
	}
}
