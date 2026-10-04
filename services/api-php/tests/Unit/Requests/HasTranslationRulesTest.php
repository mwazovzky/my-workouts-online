<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Admin\CategoryStoreRequest;
use App\Http\Requests\Admin\ExerciseStoreRequest;
use App\Rules\AssociativeArray;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasTranslationRulesTest extends TestCase
{
    #[Test]
    public function rules_cover_every_available_locale(): void
    {
        $rules = (new ExerciseStoreRequest)->rules();

        $this->assertEquals(['required', 'array', new AssociativeArray], $rules['translations']);
        $this->assertEquals(['required', 'array', new AssociativeArray], $rules['translations.en']);
        $this->assertSame(['required', 'string', 'max:255'], $rules['translations.en.name']);
        $this->assertSame(['nullable', 'string', 'max:2000'], $rules['translations.en.description']);
        $this->assertEquals(['sometimes', 'array', new AssociativeArray], $rules['translations.ru']);
        $this->assertSame(['nullable', 'string', 'max:255'], $rules['translations.ru.name']);
        $this->assertSame(['nullable', 'string', 'max:2000'], $rules['translations.ru.description']);
    }

    #[Test]
    public function rules_include_only_the_requested_fields(): void
    {
        $rules = (new CategoryStoreRequest)->rules();

        $this->assertArrayHasKey('translations.en.name', $rules);
        $this->assertArrayNotHasKey('translations.en.description', $rules);
        $this->assertArrayNotHasKey('translations.ru.description', $rules);
    }

    #[Test]
    public function a_locale_added_to_the_config_gets_optional_rules(): void
    {
        config(['app.available_locales' => ['en' => 'English', 'ru' => 'Русский', 'de' => 'Deutsch']]);

        $rules = (new ExerciseStoreRequest)->rules();

        $this->assertEquals(['sometimes', 'array', new AssociativeArray], $rules['translations.de']);
        $this->assertSame(['nullable', 'string', 'max:255'], $rules['translations.de.name']);
        $this->assertSame(['nullable', 'string', 'max:2000'], $rules['translations.de.description']);
    }

    #[Test]
    public function only_the_source_locale_name_is_required(): void
    {
        config(['app.source_locale' => 'ru']);

        $request = new ExerciseStoreRequest;
        $rules = $request->rules();

        $this->assertSame(['required', 'string', 'max:255'], $rules['translations.ru.name']);
        $this->assertSame(['nullable', 'string', 'max:255'], $rules['translations.en.name']);
        $this->assertArrayHasKey('translations.ru.name.required', $request->messages());
    }
}
