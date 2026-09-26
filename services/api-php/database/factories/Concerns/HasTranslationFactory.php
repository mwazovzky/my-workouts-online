<?php

namespace Database\Factories\Concerns;

trait HasTranslationFactory
{
    /**
     * Set a specific translation for the model.
     */
    public function withTranslation(string $field, string $value, string $locale = 'en'): static
    {
        return $this->afterCreating(function ($model) use ($field, $value, $locale) {
            $model->translations()->updateOrCreate(
                ['locale' => $locale, 'field' => $field],
                ['value' => $value]
            );
            $model->unsetRelation('translations');
        });
    }

    /**
     * Remove a specific translation (e.g., to test null handling).
     */
    public function withoutTranslation(string $field): static
    {
        return $this->afterCreating(function ($model) use ($field) {
            $model->translations()->where('field', $field)->delete();
            $model->unsetRelation('translations');
        });
    }
}
