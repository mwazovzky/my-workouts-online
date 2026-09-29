<?php

namespace Tests\Unit\Catalog;

use App\Exceptions\InvalidCatalogException;
use App\Services\Catalog\CatalogLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CatalogLoaderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../Fixtures/catalog';

    private function load(): array
    {
        return (new CatalogLoader(self::FIXTURES))->load();
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
    public function it_fails_when_the_directory_does_not_exist(): void
    {
        $this->expectException(InvalidCatalogException::class);

        (new CatalogLoader(self::FIXTURES.'/missing'))->load();
    }

    #[Test]
    public function it_fails_when_a_required_file_is_missing(): void
    {
        $this->expectException(InvalidCatalogException::class);
        $this->expectExceptionMessage('equipment.php');

        (new CatalogLoader(self::FIXTURES.'/exercises'))->load();
    }
}
