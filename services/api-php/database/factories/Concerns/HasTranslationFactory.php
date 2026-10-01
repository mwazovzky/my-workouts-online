<?php

namespace Database\Factories\Concerns;

trait HasTranslationFactory
{
    /**
     * Set a specific translation for the model. The source locale is the
     * model's own column; any other locale is a translation row.
     */
    public function withTranslation(string $field, string $value, string $locale = 'en'): static
    {
        if ($locale === config('app.source_locale')) {
            return $this->state([$field => $value]);
        }

        return $this->afterCreating(function ($model) use ($field, $value, $locale) {
            $model->translations()->updateOrCreate(
                ['locale' => $locale, 'field' => $field],
                ['value' => $value]
            );
            $model->unsetRelation('translations');
        });
    }

    /**
     * Remove a field's text in every locale (e.g., to test null handling).
     */
    public function withoutTranslation(string $field): static
    {
        return $this->state([$field => null])->afterCreating(function ($model) use ($field) {
            $model->translations()->where('field', $field)->delete();
            $model->unsetRelation('translations');
        });
    }
}
