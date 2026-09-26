<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;

trait HasTranslations
{
    /**
     * Auto-eager-load translations for every query.
     * Since original text columns are removed, translations are always needed.
     * Can be bypassed per-query with ->without('translations').
     */
    public function initializeHasTranslations(): void
    {
        $this->with = array_merge($this->with, ['translations']);
    }

    public static function bootHasTranslations(): void
    {
        static::deleting(function ($model) {
            // Keep translations on a soft delete so a "retired" record still
            // resolves its name/description; only purge on a real/force delete.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->translations()->delete();
        });
    }

    /**
     * Define which fields are translatable.
     *
     * @return array<string>
     */
    abstract public function translatableFields(): array;

    /**
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get the translated value for a field.
     * Falls back to English if the requested locale has no translation.
     */
    public function translated(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? App::getLocale();

        $translation = $this->translations
            ->where('field', $field)
            ->where('locale', $locale)
            ->first();

        if (! $translation && $locale !== 'en') {
            $translation = $this->translations
                ->where('field', $field)
                ->where('locale', 'en')
                ->first();
        }

        return $translation?->value;
    }

    /**
     * Override getAttribute to intercept translatable fields.
     */
    public function getAttribute($key): mixed
    {
        if (in_array($key, $this->translatableFields(), true)) {
            return $this->translated($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Include translatable fields when converting to array.
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach ($this->translatableFields() as $field) {
            $attributes[$field] = $this->translated($field);
        }

        return $attributes;
    }

    /**
     * Create a model with translations in multiple locales.
     *
     * Blank values (null or empty string) are skipped so reads fall back to
     * English rather than storing an empty translation.
     *
     * @param  array<string, array<string, ?string>>  $translations  e.g. ['en' => ['name' => 'Chest'], 'ru' => ['name' => 'Грудь']]
     * @param  array<string, mixed>  $attributes  Non-translatable model attributes
     */
    public static function createWithTranslations(array $translations, array $attributes = []): static
    {
        $model = static::create($attributes);

        foreach ($translations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $model->translations()->create([
                    'locale' => $locale,
                    'field' => $field,
                    'value' => $value,
                ]);
            }
        }

        return $model;
    }

    /**
     * Raw stored value for a field in a specific locale, with no fallback.
     * Intended for admin edit forms that need each locale's own value.
     */
    public function rawTranslation(string $field, string $locale): ?string
    {
        return $this->translations
            ->where('field', $field)
            ->where('locale', $locale)
            ->first()?->value;
    }

    /**
     * Raw values of a translatable field keyed by every available locale.
     *
     * @return array<string, ?string>
     */
    public function translationMap(string $field): array
    {
        $locales = array_keys(config('app.available_locales', ['en' => 'English']));

        $map = [];
        foreach ($locales as $locale) {
            $map[$locale] = $this->rawTranslation($field, $locale);
        }

        return $map;
    }

    /**
     * Update (or create) translations for the given locales and fields.
     *
     * A blank value removes that locale's translation so lookups fall back to
     * English. Only the locales/fields present in the payload are touched.
     *
     * @param  array<string, array<string, ?string>>  $translations  e.g. ['en' => ['name' => 'Chest'], 'ru' => ['name' => '']]
     */
    public function updateTranslations(array $translations): static
    {
        foreach ($translations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                if ($value === null || $value === '') {
                    $this->translations()
                        ->where('locale', $locale)
                        ->where('field', $field)
                        ->delete();

                    continue;
                }

                $this->translations()->updateOrCreate(
                    ['locale' => $locale, 'field' => $field],
                    ['value' => $value],
                );
            }
        }

        $this->load('translations');

        return $this;
    }

    /**
     * Scope to filter by a translated field value.
     */
    public function scopeWhereTranslated(Builder $query, string $field, string $value, ?string $locale = null): Builder
    {
        $locale = $locale ?? App::getLocale();

        return $query->whereHas('translations', function (Builder $q) use ($field, $value, $locale) {
            $q->where('field', $field)
                ->where('value', $value)
                ->where('locale', $locale);
        });
    }

    /**
     * Scope to filter by translated field with multiple values.
     */
    public function scopeWhereTranslatedIn(Builder $query, string $field, array $values, ?string $locale = null): Builder
    {
        $locale = $locale ?? App::getLocale();

        return $query->whereHas('translations', function (Builder $q) use ($field, $values, $locale) {
            $q->where('field', $field)
                ->where('locale', $locale)
                ->whereIn('value', $values);
        });
    }

    public function __toString(): string
    {
        return $this->translated('name') ?? '';
    }
}
