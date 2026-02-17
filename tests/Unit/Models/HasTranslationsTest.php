<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasTranslationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function translated_returns_value_for_current_locale(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        App::setLocale('en');
        $this->assertEquals('Chest', $category->translated('name'));

        App::setLocale('ru');
        $this->assertEquals('Грудь', $category->translated('name'));
    }

    #[Test]
    public function translated_falls_back_to_english_when_locale_missing(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
        ]);

        App::setLocale('ru');
        $this->assertEquals('Chest', $category->translated('name'));
    }

    #[Test]
    public function translated_returns_null_when_no_translation_exists(): void
    {
        $category = Category::create();

        $this->assertNull($category->translated('name'));
    }

    #[Test]
    public function virtual_accessor_resolves_via_translated(): void
    {
        $exercise = Exercise::createWithTranslations([
            'en' => ['name' => 'Bench Press', 'description' => 'Chest exercise.'],
            'ru' => ['name' => 'Жим лёжа', 'description' => 'Упражнение на грудь.'],
        ], [
            'rest_time_seconds' => 90,
        ]);

        App::setLocale('en');
        $this->assertEquals('Bench Press', $exercise->name);
        $this->assertEquals('Chest exercise.', $exercise->description);

        App::setLocale('ru');
        $this->assertEquals('Жим лёжа', $exercise->name);
        $this->assertEquals('Упражнение на грудь.', $exercise->description);
    }

    #[Test]
    public function to_array_includes_translatable_fields(): void
    {
        $program = Program::createWithTranslations([
            'en' => ['name' => 'Beginner', 'description' => 'A beginner program.'],
        ]);

        App::setLocale('en');
        $array = $program->toArray();

        $this->assertEquals('Beginner', $array['name']);
        $this->assertEquals('A beginner program.', $array['description']);
    }

    #[Test]
    public function create_with_translations_creates_model_and_translations(): void
    {
        $equipment = Equipment::createWithTranslations([
            'en' => ['name' => 'Barbell', 'unit' => 'kg'],
            'ru' => ['name' => 'Штанга', 'unit' => 'кг'],
        ]);

        $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
        $this->assertDatabaseCount('translations', 4);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'equipment',
            'translatable_id' => $equipment->id,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Barbell',
        ]);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'equipment',
            'translatable_id' => $equipment->id,
            'locale' => 'ru',
            'field' => 'unit',
            'value' => 'кг',
        ]);
    }

    #[Test]
    public function scope_where_translated_filters_by_value(): void
    {
        Category::createWithTranslations(['en' => ['name' => 'Chest'], 'ru' => ['name' => 'Грудь']]);
        Category::createWithTranslations(['en' => ['name' => 'Legs'], 'ru' => ['name' => 'Ноги']]);

        $results = Category::whereTranslated('name', 'Chest', 'en')->get();
        $this->assertCount(1, $results);

        $results = Category::whereTranslated('name', 'Грудь', 'ru')->get();
        $this->assertCount(1, $results);

        $results = Category::whereTranslated('name', 'Nonexistent', 'en')->get();
        $this->assertCount(0, $results);
    }

    #[Test]
    public function scope_where_translated_in_filters_by_multiple_values(): void
    {
        Category::createWithTranslations(['en' => ['name' => 'Chest']]);
        Category::createWithTranslations(['en' => ['name' => 'Legs']]);
        Category::createWithTranslations(['en' => ['name' => 'Abs']]);

        $results = Category::whereTranslatedIn('name', ['Chest', 'Legs'], 'en')->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function translations_are_auto_eager_loaded(): void
    {
        Category::createWithTranslations(['en' => ['name' => 'Chest']]);

        $category = Category::first();
        $this->assertTrue($category->relationLoaded('translations'));
    }

    #[Test]
    public function deleting_model_cascades_to_translations(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        $this->assertDatabaseCount('translations', 2);

        $category->delete();

        $this->assertDatabaseCount('translations', 0);
    }

    #[Test]
    public function to_string_returns_name(): void
    {
        $category = Category::createWithTranslations(['en' => ['name' => 'Chest']]);

        $this->assertEquals('Chest', (string) $category);
    }

    #[Test]
    public function factory_creates_translations_automatically(): void
    {
        $exercise = Exercise::factory()->create();

        $this->assertNotNull($exercise->name);
        $this->assertNotNull($exercise->description);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'exercise',
            'translatable_id' => $exercise->id,
            'locale' => 'en',
            'field' => 'name',
        ]);
    }

    #[Test]
    public function factory_with_translation_overrides_default(): void
    {
        $exercise = Exercise::factory()
            ->withTranslation('name', 'Custom Name')
            ->create();

        $this->assertEquals('Custom Name', $exercise->name);
    }

    #[Test]
    public function factory_without_translation_removes_field(): void
    {
        $program = Program::factory()
            ->withoutTranslation('description')
            ->create();

        $this->assertNull($program->description);
    }

    #[Test]
    public function workout_template_translatable(): void
    {
        $template = WorkoutTemplate::createWithTranslations([
            'en' => ['name' => 'Full Body', 'description' => 'A full body session.'],
            'ru' => ['name' => 'Всё тело', 'description' => 'Тренировка на всё тело.'],
        ]);

        App::setLocale('ru');
        $this->assertEquals('Всё тело', $template->name);
        $this->assertEquals('Тренировка на всё тело.', $template->description);
    }

    #[Test]
    public function translated_accepts_explicit_locale_parameter(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        App::setLocale('en');
        $this->assertEquals('Грудь', $category->translated('name', 'ru'));
        $this->assertEquals('Chest', $category->translated('name', 'en'));
    }

    #[Test]
    public function resource_returns_translated_content_for_locale(): void
    {
        $user = \App\Models\User::factory()->create(['locale' => 'ru']);

        $exercise = Exercise::createWithTranslations([
            'en' => ['name' => 'Squat', 'description' => 'Leg exercise.'],
            'ru' => ['name' => 'Приседания', 'description' => 'Упражнение на ноги.'],
        ], [
            'rest_time_seconds' => 90,
        ]);

        App::setLocale('ru');
        $this->assertEquals('Приседания', $exercise->name);

        App::setLocale('en');
        $this->assertEquals('Squat', $exercise->name);
    }
}
