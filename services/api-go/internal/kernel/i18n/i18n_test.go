package i18n_test

import (
	"regexp"
	"slices"
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
)

func newBundle(t *testing.T) *i18n.Bundle {
	t.Helper()

	b, err := i18n.NewBundle()
	if err != nil {
		t.Fatal(err)
	}

	return b
}

func TestNegotiate(t *testing.T) {
	t.Parallel()

	supported := []i18n.Locale{i18n.EN, i18n.RU}

	tests := []struct {
		header string
		want   i18n.Locale
	}{
		{"", i18n.EN},
		{"ru", i18n.RU},
		{"ru-RU,ru;q=0.9,en;q=0.5", i18n.RU},
		{"en;q=0.4,ru;q=0.8", i18n.RU},
		{"fr-FR,de;q=0.8", i18n.EN},
		{"de", i18n.EN},
		{"de,ru;q=0.1", i18n.RU},
		{"*", i18n.EN},
		{"not a header;;;q=x", i18n.EN},
	}

	for _, tt := range tests {
		if got := i18n.Negotiate(tt.header, supported, i18n.EN); got != tt.want {
			t.Errorf("Negotiate(%q) = %q, want %q", tt.header, got, tt.want)
		}
	}
}

func TestT(t *testing.T) {
	t.Parallel()

	b := newBundle(t)

	tests := []struct {
		name   string
		locale i18n.Locale
		key    string
		args   map[string]string
		want   string
	}{
		{"validation message", i18n.EN, "validation.required", map[string]string{"attribute": "name"}, "The name field is required."},
		{"translated", i18n.RU, "validation.required", map[string]string{"attribute": "name"}, "Поле name обязательно."},
		{"longest placeholder first", i18n.EN, "validation.between.numeric", map[string]string{"attribute": "zone", "min": "1", "max": "5"}, "The zone field must be between 1 and 5."},
		{"sentence key", i18n.RU, "Record not found.", nil, "Запись не найдена."},
		{"unknown key is returned as is", i18n.RU, "Nothing like this.", nil, "Nothing like this."},
		{"unknown locale falls back", i18n.Locale("de"), "validation.required", map[string]string{"attribute": "name"}, "The name field is required."},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			if got := b.T(tt.locale, tt.key, tt.args); got != tt.want {
				t.Errorf("T() = %q, want %q", got, tt.want)
			}
		})
	}
}

func TestPlaceholderCase(t *testing.T) {
	t.Parallel()

	// No catalog message uses :Attribute, so check the rule on an unknown
	// key, which T returns as its own message.
	got := newBundle(t).T(i18n.EN, ":Attribute, :attribute and :ATTRIBUTE", map[string]string{"attribute": "effort value"})
	if want := "Effort value, effort value and EFFORT VALUE"; got != want {
		t.Errorf("T() = %q, want %q", got, want)
	}
}

// Every key the backend uses exists in every locale, with the same
// placeholders, and nothing but the untranslated exceptions is left in
// English.
func TestCatalogsAreComplete(t *testing.T) {
	t.Parallel()

	b := newBundle(t)
	placeholders := regexp.MustCompile(`:[a-z]+`)

	if diff := cmp.Diff(b.Keys(i18n.EN), b.Keys(i18n.RU)); diff != "" {
		t.Fatalf("key sets differ (-en +ru):\n%s", diff)
	}

	// The PHP API ships these untranslated too.
	untranslated := []string{"Send the workout"}

	for _, key := range b.Keys(i18n.EN) {
		en, ru := b.T(i18n.EN, key, nil), b.T(i18n.RU, key, nil)

		if !slices.Equal(sortedMatches(placeholders, en), sortedMatches(placeholders, ru)) {
			t.Errorf("%q: placeholders differ: en %q, ru %q", key, en, ru)
		}

		if en == ru && !slices.Contains(untranslated, key) && placeholders.ReplaceAllString(en, "") != "" {
			t.Errorf("%q has no Russian translation", key)
		}
	}
}

func TestLocalesAndNames(t *testing.T) {
	t.Parallel()

	b := newBundle(t)

	if got := b.Locales(); !slices.Equal(got, []i18n.Locale{i18n.EN, i18n.RU}) {
		t.Errorf("Locales() = %v", got)
	}

	if diff := cmp.Diff(map[i18n.Locale]string{"en": "English", "ru": "Русский"}, b.Names()); diff != "" {
		t.Errorf("Names() (-want +got):\n%s", diff)
	}

	if !b.Has(i18n.RU, "auth.failed") || b.Has(i18n.RU, "auth.nothing") {
		t.Error("Has() does not reflect the catalog")
	}
}

func sortedMatches(re *regexp.Regexp, s string) []string {
	matches := re.FindAllString(s, -1)
	slices.Sort(matches)

	return slices.Compact(matches)
}
