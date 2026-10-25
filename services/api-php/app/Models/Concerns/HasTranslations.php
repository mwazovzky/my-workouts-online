<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

/**
 * Translatable fields keep their source text in the model's own columns. The
 * translations table holds the other locales, so a record without any
 * translation still reads the same in every locale.
 *
 * @phpstan-require-extends Model
 */
trait HasTranslations
{
    /**
     * Auto-eager-load translations for every query.
     * Every read of a translatable field looks for the requested locale first.
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
     * Locale the model's own columns are written in.
     */
    public static function sourceLocale(): string
    {
        return config('app.source_locale', 'en');
    }

    /**
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get the translated value for a field.
     * Falls back to the source text if the requested locale has no translation.
     */
    public function translated(string $field, ?string $locale = null): ?string
    {
        return $this->storedTranslation($field, $locale ?? App::getLocale())
            ?? $this->sourceText($field);
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
     * The source locale is written to the model's columns, the others to the
     * translations table. Blank values (null or empty string) are skipped so
     * reads fall back to the source text rather than an empty translation.
     *
     * @param  array<string, array<string, ?string>>  $translations  e.g. ['en' => ['name' => 'Chest'], 'ru' => ['name' => 'Грудь']]
     * @param  array<string, mixed>  $attributes  Non-translatable model attributes
     */
    public static function createWithTranslations(array $translations, array $attributes = []): static
    {
        $model = static::query()->make($attributes);
        $model->fillSourceText($translations[static::sourceLocale()] ?? []);
        $model->save();

        foreach (Arr::except($translations, static::sourceLocale()) as $locale => $fields) {
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
        return $locale === static::sourceLocale()
            ? $this->sourceText($field)
            : $this->storedTranslation($field, $locale);
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
     * the source text. Only the locales/fields present in the payload are touched.
     *
     * @param  array<string, array<string, ?string>>  $translations  e.g. ['en' => ['name' => 'Chest'], 'ru' => ['name' => '']]
     */
    public function updateTranslations(array $translations): static
    {
        $this->fillSourceText($translations[static::sourceLocale()] ?? []);
        $this->save();

        foreach (Arr::except($translations, static::sourceLocale()) as $locale => $fields) {
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
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereTranslated(Builder $query, string $field, string $value, ?string $locale = null): Builder
    {
        return $this->scopeWhereTranslatedIn($query, $field, [$value], $locale);
    }

    /**
     * Scope to filter by translated field with multiple values.
     *
     * @param  Builder<static>  $query
     * @param  array<int, string>  $values
     * @return Builder<static>
     */
    public function scopeWhereTranslatedIn(Builder $query, string $field, array $values, ?string $locale = null): Builder
    {
        $locale = $locale ?? App::getLocale();

        if ($locale === static::sourceLocale()) {
            return $query->whereIn($this->qualifyColumn($field), $values);
        }

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

    /**
     * @param  array<string, ?string>  $fields
     */
    private function fillSourceText(array $fields): void
    {
        foreach (Arr::only($fields, $this->translatableFields()) as $field => $value) {
            $this->setAttribute($field, $value === '' ? null : $value);
        }
    }

    private function sourceText(string $field): ?string
    {
        return $this->attributes[$field] ?? null;
    }

    private function storedTranslation(string $field, string $locale): ?string
    {
        return $this->translations
            ->where('field', $field)
            ->where('locale', $locale)
            ->first()?->value;
    }
}
