<?php

namespace Tests\Feature\Migrations;

use App\Models\Exercise;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MoveSourceTranslationsMigrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Back to the schema the move ran against: columns added, name still optional.
        $this->migration('2026_09_29_000003_require_name_on_catalog_tables')->down();
    }

    #[Test]
    public function it_moves_english_rows_into_the_columns(): void
    {
        [$equipmentId, $exerciseId, $retiredId] = $this->seedRecordsInTheOldShape();

        $this->migration('2026_09_29_000002_move_source_translations_into_catalog_columns')->up();

        $this->assertDatabaseHas('equipment', ['id' => $equipmentId, 'name' => 'Barbell']);
        $this->assertDatabaseHas('exercises', [
            'id' => $exerciseId,
            'name' => 'Bench Press',
            'description' => 'Press the bar.',
        ]);
        $this->assertDatabaseHas('exercises', ['id' => $retiredId, 'name' => 'Upright Row', 'description' => null]);

        $this->assertDatabaseMissing('translations', ['locale' => 'en']);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'exercise',
            'translatable_id' => $exerciseId,
            'locale' => 'ru',
            'field' => 'name',
            'value' => 'Жим лёжа',
        ]);
        $this->assertDatabaseCount('translations', 2);

        $this->migration('2026_09_29_000003_require_name_on_catalog_tables')->up();

        $exercise = Exercise::query()->findOrFail($exerciseId);

        App::setLocale('en');
        $this->assertSame('Bench Press', $exercise->name);

        App::setLocale('ru');
        $this->assertSame('Жим лёжа', $exercise->name);
        $this->assertSame('Press the bar.', $exercise->description);
    }

    #[Test]
    public function it_changes_nothing_when_a_record_has_no_english_name(): void
    {
        $this->seedRecordsInTheOldShape();

        $categoryId = DB::table('categories')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $this->translation('category', $categoryId, 'ru', 'name', 'Грудь');

        try {
            $this->migration('2026_09_29_000002_move_source_translations_into_catalog_columns')->up();
            $this->fail('The move ran with a record that has no English name.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("categories (ids {$categoryId})", $exception->getMessage());
        }

        $this->assertDatabaseCount('translations', 7);
        $this->assertDatabaseMissing('equipment', ['name' => 'Barbell']);
    }

    #[Test]
    public function rolling_back_restores_the_english_rows(): void
    {
        [$equipmentId, $exerciseId] = $this->seedRecordsInTheOldShape();

        $move = $this->migration('2026_09_29_000002_move_source_translations_into_catalog_columns');
        $move->up();
        $move->down();

        $this->assertDatabaseCount('translations', 6);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'equipment',
            'translatable_id' => $equipmentId,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Barbell',
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'exercise',
            'translatable_id' => $exerciseId,
            'locale' => 'en',
            'field' => 'description',
            'value' => 'Press the bar.',
        ]);
    }

    /**
     * @return array{0: int, 1: int, 2: int} Equipment, exercise and retired exercise ids
     */
    private function seedRecordsInTheOldShape(): array
    {
        $timestamps = ['created_at' => now(), 'updated_at' => now()];

        $equipmentId = DB::table('equipment')->insertGetId(['difficulty_unit' => 'kilograms'] + $timestamps);
        $exercise = ['equipment_id' => $equipmentId, 'effort_type' => 'repetitions', 'rest_time_seconds' => 90] + $timestamps;
        $exerciseId = DB::table('exercises')->insertGetId($exercise);
        $retiredId = DB::table('exercises')->insertGetId($exercise + ['deleted_at' => now()]);

        $this->translation('equipment', $equipmentId, 'en', 'name', 'Barbell');
        $this->translation('equipment', $equipmentId, 'ru', 'name', 'Штанга');
        $this->translation('exercise', $exerciseId, 'en', 'name', 'Bench Press');
        $this->translation('exercise', $exerciseId, 'en', 'description', 'Press the bar.');
        $this->translation('exercise', $exerciseId, 'ru', 'name', 'Жим лёжа');
        $this->translation('exercise', $retiredId, 'en', 'name', 'Upright Row');

        return [$equipmentId, $exerciseId, $retiredId];
    }

    private function translation(string $type, int $id, string $locale, string $field, string $value): void
    {
        DB::table('translations')->insert([
            'translatable_type' => $type,
            'translatable_id' => $id,
            'locale' => $locale,
            'field' => $field,
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migration(string $name): Migration
    {
        return require database_path("migrations/{$name}.php");
    }
}
