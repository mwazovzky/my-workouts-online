<?php

namespace App\Http\Requests\Admin\Concerns;

trait HasTranslationRules
{
    /**
     * @var array<string, int>
     */
    private const TRANSLATION_MAX_LENGTH = ['name' => 255, 'description' => 2000];

    /**
     * Rules for the `translations` payload, one group per available locale.
     *
     * The source locale must carry a name; every other value is optional, so
     * a blank one removes that translation and reads fall back.
     *
     * @param  array<int, string>  $fields  Translatable fields, e.g. ['name', 'description']
     * @return array<string, array<int, string>>
     */
    protected function translationRules(array $fields): array
    {
        $rules = ['translations' => ['required', 'array']];

        foreach (array_keys(config('app.available_locales', [])) as $locale) {
            $isSource = $locale === $this->sourceLocale();

            $rules["translations.{$locale}"] = [$isSource ? 'required' : 'sometimes', 'array'];

            foreach ($fields as $field) {
                $rules["translations.{$locale}.{$field}"] = [
                    $isSource && $field === 'name' ? 'required' : 'nullable',
                    'string',
                    'max:'.self::TRANSLATION_MAX_LENGTH[$field],
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function translationMessages(): array
    {
        return [
            "translations.{$this->sourceLocale()}.name.required" => __('An English name is required.'),
        ];
    }

    private function sourceLocale(): string
    {
        return config('app.fallback_locale', 'en');
    }
}
