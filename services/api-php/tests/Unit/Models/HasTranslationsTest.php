<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\WorkoutTemplate;
use Illuminate\Database\LazyLoadingViolationException;
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
    public function translated_falls_back_to_the_source_text_when_locale_missing(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
        ]);

        App::setLocale('ru');
        $this->assertEquals('Chest', $category->translated('name'));
    }

    #[Test]
    public function translated_returns_null_when_no_text_exists(): void
    {
        $program = Program::createWithTranslations(['en' => ['name' => 'Strength']]);

        $this->assertNull($program->translated('description'));

        App::setLocale('ru');
        $this->assertNull($program->translated('description'));
    }

    #[Test]
    public function a_record_without_translations_reads_the_same_in_every_locale(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Жим гири', 'description' => 'Моя версия.']);

        $this->assertDatabaseCount('translations', 0);

        foreach (['en', 'ru'] as $locale) {
            App::setLocale($locale);

            $this->assertSame('Жим гири', $exercise->fresh()->name);
            $this->assertSame('Моя версия.', $exercise->fresh()->description);
        }
    }

    #[Test]
    public function assigning_a_translatable_field_writes_the_source_text(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        $category->name = 'Upper Chest';
        $category->save();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Upper Chest']);
        $this->assertSame('Upper Chest', $category->fresh()->translated('name', 'en'));
        $this->assertSame('Грудь', $category->fresh()->translated('name', 'ru'));
    }

    #[Test]
    public function virtual_accessor_resolves_via_translated(): void
    {
        $exercise = Exercise::createWithTranslations([
            'en' => ['name' => 'Bench Press', 'description' => 'Chest exercise.'],
            'ru' => ['name' => 'Жим лёжа', 'description' => 'Упражнение на грудь.'],
        ], [
            'equipment_id' => Equipment::factory()->create()->id,
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
        $program = Program::createWithTranslations([
            'en' => ['name' => 'Strength', 'description' => 'Three days a week.'],
            'ru' => ['name' => 'Сила', 'description' => 'Три дня в неделю.'],
        ]);

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'name' => 'Strength',
            'description' => 'Three days a week.',
        ]);
        $this->assertDatabaseCount('translations', 2);

        $this->assertDatabaseMissing('translations', [
            'translatable_type' => 'program',
            'translatable_id' => $program->id,
            'locale' => 'en',
        ]);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'program',
            'translatable_id' => $program->id,
            'locale' => 'ru',
            'field' => 'description',
            'value' => 'Три дня в неделю.',
        ]);
    }

    #[Test]
    public function create_with_translations_skips_blank_values(): void
    {
        $exercise = Exercise::createWithTranslations([
            'en' => ['name' => 'Bicycle Training', 'description' => 'Steady-state cycling.'],
            'ru' => ['name' => 'Велотренировка', 'description' => null],
        ], [
            'equipment_id' => Equipment::factory()->create()->id,
            'rest_time_seconds' => 0,
        ]);

        $this->assertDatabaseMissing('translations', [
            'translatable_id' => $exercise->id,
            'locale' => 'ru',
            'field' => 'description',
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_id' => $exercise->id,
            'locale' => 'ru',
            'field' => 'name',
            'value' => 'Велотренировка',
        ]);

        App::setLocale('ru');
        $this->assertEquals('Steady-state cycling.', $exercise->description);
    }

    #[Test]
    public function update_translations_updates_existing_and_creates_missing(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        $category->updateTranslations([
            'en' => ['name' => 'Upper Chest'],
            'ru' => ['name' => 'Верхняя грудь'],
        ]);

        $this->assertEquals('Upper Chest', $category->translated('name', 'en'));
        $this->assertEquals('Верхняя грудь', $category->translated('name', 'ru'));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Upper Chest']);
        $this->assertDatabaseCount('translations', 1);
    }

    #[Test]
    public function update_translations_creates_translation_for_new_locale(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
        ]);

        $category->updateTranslations([
            'ru' => ['name' => 'Грудь'],
        ]);

        $this->assertEquals('Грудь', $category->translated('name', 'ru'));
        $this->assertEquals('Chest', $category->translated('name', 'en'));
        $this->assertDatabaseCount('translations', 1);
    }

    #[Test]
    public function update_translations_removes_blank_values_for_fallback(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        $category->updateTranslations([
            'ru' => ['name' => ''],
        ]);

        $this->assertDatabaseMissing('translations', [
            'translatable_id' => $category->id,
            'locale' => 'ru',
            'field' => 'name',
        ]);

        App::setLocale('ru');
        $this->assertEquals('Chest', $category->translated('name'));
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
    public function reading_a_field_without_loaded_translations_is_rejected(): void
    {
        Category::createWithTranslations(['en' => ['name' => 'Chest']]);
        Category::createWithTranslations(['en' => ['name' => 'Back']]);

        $categories = Category::query()->without('translations')->get();

        $this->expectException(LazyLoadingViolationException::class);

        $categories->first()->name;
    }

    #[Test]
    public function deleting_model_cascades_to_translations(): void
    {
        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        $this->assertDatabaseCount('translations', 1);

        $category->delete();

        $this->assertDatabaseCount('translations', 0);
    }

    #[Test]
    public function deleting_a_record_without_translations_leaves_nothing_behind(): void
    {
        $category = Category::createWithTranslations(['en' => ['name' => 'Chest']]);

        $category->delete();

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('translations', 0);
    }

    #[Test]
    public function to_string_returns_name(): void
    {
        $category = Category::createWithTranslations(['en' => ['name' => 'Chest']]);

        $this->assertEquals('Chest', (string) $category);
    }

    #[Test]
    public function factory_creates_source_text_automatically(): void
    {
        $exercise = Exercise::factory()->create();

        $this->assertNotSame('', $exercise->name);
        $this->assertNotNull($exercise->description);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'name' => $exercise->name,
        ]);
        $this->assertDatabaseCount('translations', 0);
    }

    #[Test]
    public function factory_with_translation_in_another_locale_creates_a_row(): void
    {
        $exercise = Exercise::factory()
            ->withTranslation('name', 'Bench Press')
            ->withTranslation('name', 'Жим лёжа', 'ru')
            ->create();

        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'name' => 'Bench Press']);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'exercise',
            'translatable_id' => $exercise->id,
            'locale' => 'ru',
            'field' => 'name',
            'value' => 'Жим лёжа',
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
            'equipment_id' => Equipment::factory()->create()->id,
            'rest_time_seconds' => 90,
        ]);

        App::setLocale('ru');
        $this->assertEquals('Приседания', $exercise->name);

        App::setLocale('en');
        $this->assertEquals('Squat', $exercise->name);
    }
}
