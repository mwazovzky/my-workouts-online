<?php

namespace Tests\Unit\Catalog;

use App\Enums\DifficultyUnit;
use App\Enums\EffortType;
use App\Enums\Weekday;
use App\Exceptions\InvalidCatalogException;
use App\Services\Catalog\CatalogLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesSharedCatalog;
use Tests\TestCase;

class CatalogLoaderTest extends TestCase
{
    use UsesSharedCatalog;

    /**
     * @param  array<string, string|null>  $files  Replaced files of the valid catalog
     * @return array<string, mixed>
     */
    private function load(array $files = []): array
    {
        $path = $files === [] ? $this->validCatalogPath() : $this->catalogCopy($files);

        return (new CatalogLoader($path))->load();
    }

    #[Test]
    public function it_loads_every_record_type(): void
    {
        $catalog = $this->load();

        $this->assertSame(['barbell', 'bodyweight', 'indoor-bike'], array_column($catalog['equipment'], 'slug'));
        $this->assertSame(['chest', 'legs', 'cardio'], array_column($catalog['categories'], 'slug'));
        $this->assertSame(['starter'], array_column($catalog['programs'], 'slug'));
    }

    #[Test]
    public function it_merges_directory_files_in_file_name_order(): void
    {
        $catalog = $this->load();

        $this->assertSame(
            ['easy-ride', 'bench-press', 'squat', 'push-up'],
            array_column($catalog['exercises'], 'slug'),
        );
        $this->assertSame(['easy-spin', 'full-body'], array_column($catalog['workout_templates'], 'slug'));
    }

    #[Test]
    public function it_fills_optional_fields_with_defaults(): void
    {
        $exercises = array_column($this->load()['exercises'], null, 'slug');

        $this->assertSame([], $exercises['squat']['previous_names']);
        $this->assertSame(['Old Bench Press'], $exercises['bench-press']['previous_names']);
        $this->assertNull($exercises['easy-ride']['rest_time_seconds']);
        $this->assertSame(120, $exercises['bench-press']['rest_time_seconds']);
    }

    #[Test]
    public function it_expands_the_set_count_shorthand(): void
    {
        $templates = array_column($this->load()['workout_templates'], null, 'slug');

        $this->assertSame([
            'exercise' => 'bench-press',
            'sets' => [
                ['effort' => 8, 'difficulty' => null],
                ['effort' => 8, 'difficulty' => null],
                ['effort' => 8, 'difficulty' => null],
            ],
        ], $templates['full-body']['activities'][0]);

        $this->assertSame([
            ['effort' => 600, 'difficulty' => 2],
            ['effort' => 600, 'difficulty' => 2],
        ], $templates['easy-spin']['activities'][1]['sets']);
    }

    #[Test]
    public function it_keeps_explicit_sets_and_defaults_their_difficulty_to_null(): void
    {
        $templates = array_column($this->load()['workout_templates'], null, 'slug');

        $this->assertSame([
            ['effort' => 10, 'difficulty' => null],
            ['effort' => 8, 'difficulty' => null],
        ], $templates['full-body']['activities'][1]['sets']);

        $this->assertSame(
            [['effort' => 1800, 'difficulty' => 1]],
            $templates['easy-spin']['activities'][0]['sets'],
        );
    }

    #[Test]
    public function it_returns_a_retired_list_for_every_type(): void
    {
        $retired = $this->load()['retired'];

        $this->assertSame(CatalogLoader::TYPES, array_keys($retired));
        $this->assertSame([['slug' => 'old-rack', 'names' => ['Old Rack']]], $retired['equipment']);
        $this->assertSame([], $retired['exercises']);
    }

    #[Test]
    public function it_maps_enum_values_to_their_cases(): void
    {
        $catalog = $this->load();

        $this->assertSame(DifficultyUnit::Kilograms, $catalog['equipment'][0]['difficulty_unit']);
        $this->assertSame(EffortType::Duration, $catalog['exercises'][0]['effort_type']);
        $this->assertSame(Weekday::Monday, $catalog['programs'][0]['schedule'][0]['weekday']);
    }

    #[Test]
    public function it_leaves_an_unknown_enum_value_for_the_validator(): void
    {
        $catalog = $this->load(['equipment.yaml' => "- { slug: barbell, difficulty_unit: beats }\n"]);

        $this->assertSame('beats', $catalog['equipment'][0]['difficulty_unit']);
    }

    #[Test]
    public function it_keeps_a_retired_type_the_catalog_does_not_have(): void
    {
        $retired = $this->load(['retired.yaml' => "machines: []\n"])['retired'];

        $this->assertSame([], $retired['machines']);
        $this->assertSame([], $retired['equipment']);
    }

    #[Test]
    public function it_returns_an_empty_retired_list_without_the_file(): void
    {
        $retired = $this->load(['retired.yaml' => null])['retired'];

        $this->assertSame(array_fill_keys(CatalogLoader::TYPES, []), $retired);
    }

    #[Test]
    public function it_fails_when_the_directory_does_not_exist(): void
    {
        $this->expectException(InvalidCatalogException::class);

        (new CatalogLoader($this->validCatalogPath().'/missing'))->load();
    }

    #[Test]
    public function it_fails_when_a_required_file_is_missing(): void
    {
        $this->expectException(InvalidCatalogException::class);
        $this->expectExceptionMessage('equipment.yaml');

        (new CatalogLoader($this->validCatalogPath().'/exercises'))->load();
    }

    /**
     * @return array<string, array{array<string, string|null>, string}>
     */
    public static function brokenFiles(): array
    {
        return [
            'no files in a directory' => [
                ['exercises/cardio.yaml' => null, 'exercises/strength.yaml' => null],
                'Catalog directory [exercises] has no data files.',
            ],
            'not a list' => [['categories.yaml' => "slug: chest\n"], 'Catalog file [categories.yaml] must hold a list of records.'],
            'syntax error' => [['categories.yaml' => "- [unclosed\n"], 'Catalog file [categories.yaml]: '],
            'unknown record key' => [
                ['categories.yaml' => "- { slug: chest, colour: red }\n"],
                'Catalog file [categories.yaml: categories [chest]]: unknown key "colour".',
            ],
            'unknown activity key' => [
                ['workout-templates/strength.yaml' => "- { slug: x, activities: [{ exercise: squat, reps: 5 }] }\n"],
                'workout_templates [x] activity 1]: unknown key "reps".',
            ],
            'unknown set key' => [
                ['workout-templates/strength.yaml' => "- { slug: x, activities: [{ exercise: squat, sets: [{ effort: 5, weight: 60 }] }] }\n"],
                'workout_templates [x] activity 1 set 1]: unknown key "weight".',
            ],
            'unknown schedule key' => [
                ['programs.yaml' => "- { slug: p, schedule: [{ weekday: monday, template: full-body, time: 7 }] }\n"],
                'programs [p] schedule]: unknown key "time".',
            ],
            'unknown retired key' => [
                ['retired.yaml' => "equipment: [{ slug: old-rack, names: [Old Rack], reason: x }]\n"],
                'Catalog file [retired.yaml: equipment]: unknown key "reason".',
            ],
            'retired not a mapping' => [['retired.yaml' => "- equipment\n"], 'Catalog file [retired.yaml] must map record types to lists.'],
            'retired type not a list' => [['retired.yaml' => "equipment: old-rack\n"], 'Catalog file [retired.yaml]: equipment must be a list.'],
        ];
    }

    /**
     * @param  array<string, string|null>  $files
     */
    #[Test]
    #[DataProvider('brokenFiles')]
    public function it_rejects_a_broken_file(array $files, string $message): void
    {
        $this->expectException(InvalidCatalogException::class);

        try {
            $this->load($files);
        } catch (InvalidCatalogException $e) {
            $this->assertStringContainsString($message, $e->violations[0]);

            throw $e;
        }
    }
}
