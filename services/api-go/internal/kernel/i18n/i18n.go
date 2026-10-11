// Package i18n negotiates the response locale and translates backend
// messages. The catalogs are locales/<locale>.json, ported from the PHP API's
// lang files: Laravel's validation, auth and passwords files flattened to
// "validation.required"-style keys, plus the English sentences the backend
// translates, keyed by themselves. Placeholders follow Laravel: :name,
// :Name (first letter upper-cased) and :NAME (all upper-cased).
package i18n

import (
	"context"
	"embed"
	"encoding/json"
	"fmt"
	"path"
	"slices"
	"sort"
	"strings"
	"unicode"
	"unicode/utf8"

	"golang.org/x/text/language"
)

// Locale is a supported language, as a lowercase base-language tag.
type Locale string

// The supported locales. Source is the language of the catalog's own
// columns and of every message key.
const (
	EN     Locale = "en"
	RU     Locale = "ru"
	Source        = EN
)

// names are the locales' own names, as getMeta lists them.
var names = map[Locale]string{EN: "English", RU: "Русский"}

//go:embed locales/*.json
var catalogs embed.FS

// Bundle holds every locale's messages.
type Bundle struct {
	messages map[Locale]map[string]string
	locales  []Locale
}

// NewBundle loads the embedded catalogs. Every catalog must hold the same
// keys and have a name in names.
func NewBundle() (*Bundle, error) {
	files, err := catalogs.ReadDir("locales")
	if err != nil {
		return nil, fmt.Errorf("i18n: %w", err)
	}

	b := &Bundle{messages: map[Locale]map[string]string{}}

	for _, file := range files {
		locale := Locale(strings.TrimSuffix(file.Name(), path.Ext(file.Name())))
		if _, ok := names[locale]; !ok {
			return nil, fmt.Errorf("i18n: locales/%s has no name", file.Name())
		}

		data, err := catalogs.ReadFile("locales/" + file.Name())
		if err != nil {
			return nil, fmt.Errorf("i18n: %w", err)
		}

		var messages map[string]string
		if err := json.Unmarshal(data, &messages); err != nil {
			return nil, fmt.Errorf("i18n: locales/%s: %w", file.Name(), err)
		}

		b.messages[locale] = messages
		b.locales = append(b.locales, locale)
	}

	slices.Sort(b.locales)

	if _, ok := b.messages[Source]; !ok {
		return nil, fmt.Errorf("i18n: no catalog for the source locale %q", Source)
	}

	return b, nil
}

// Locales returns the supported locales, sorted.
func (b *Bundle) Locales() []Locale { return slices.Clone(b.locales) }

// Names returns each supported locale's own name.
func (b *Bundle) Names() map[Locale]string {
	out := make(map[Locale]string, len(b.locales))
	for _, locale := range b.locales {
		out[locale] = names[locale]
	}

	return out
}

// Keys returns every message key of locale, sorted.
func (b *Bundle) Keys(locale Locale) []string {
	keys := make([]string, 0, len(b.messages[locale]))
	for key := range b.messages[locale] {
		keys = append(keys, key)
	}

	sort.Strings(keys)

	return keys
}

// Has reports whether locale's catalog has key.
func (b *Bundle) Has(locale Locale, key string) bool {
	_, ok := b.messages[locale][key]

	return ok
}

// T translates key into locale and fills its placeholders from args. A key
// missing from locale falls back to the source locale, then to the key
// itself, as Laravel's __() does.
func (b *Bundle) T(locale Locale, key string, args map[string]string) string {
	message, ok := b.messages[locale][key]
	if !ok {
		message, ok = b.messages[Source][key]
	}

	if !ok {
		message = key
	}

	return replace(message, args)
}

// replace fills :name placeholders, longest name first so :max never eats
// the start of :maximum.
func replace(message string, args map[string]string) string {
	if len(args) == 0 {
		return message
	}

	keys := make([]string, 0, len(args))
	for key := range args {
		keys = append(keys, key)
	}

	sort.Slice(keys, func(i, j int) bool { return len(keys[i]) > len(keys[j]) })

	pairs := make([]string, 0, len(keys)*6)
	for _, key := range keys {
		value := args[key]
		pairs = append(pairs,
			":"+strings.ToUpper(key), strings.ToUpper(value),
			":"+upperFirst(key), upperFirst(value),
			":"+key, value,
		)
	}

	return strings.NewReplacer(pairs...).Replace(message)
}

func upperFirst(s string) string {
	r, size := utf8.DecodeRuneInString(s)
	if r == utf8.RuneError {
		return s
	}

	return string(unicode.ToUpper(r)) + s[size:]
}

// Negotiate picks the response locale from an Accept-Language header: the
// highest-quality language whose base language is supported (ru-RU matches
// ru), else fallback. A malformed header counts as missing.
func Negotiate(acceptLanguage string, supported []Locale, fallback Locale) Locale {
	tags, _, err := language.ParseAcceptLanguage(acceptLanguage)
	if err != nil {
		return fallback
	}

	for _, tag := range tags {
		base, _ := tag.Base()
		if locale := Locale(base.String()); slices.Contains(supported, locale) {
			return locale
		}
	}

	return fallback
}

type localeKey struct{}

// WithLocale returns ctx carrying the request's negotiated locale.
func WithLocale(ctx context.Context, locale Locale) context.Context {
	return context.WithValue(ctx, localeKey{}, locale)
}

// FromContext returns the locale in ctx, or Source outside a request.
func FromContext(ctx context.Context) Locale {
	if locale, ok := ctx.Value(localeKey{}).(Locale); ok {
		return locale
	}

	return Source
}
