<?php

namespace Tests\Feature\Catalog;

use App\Services\Catalog\CatalogExporter;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Exports the test fixture catalog into a temporary directory.
 */
class CatalogExportCommandTest extends TestCase
{
    private string $target;

    /**
     * The repository's catalog/ directory, before the tests point elsewhere.
     */
    private string $sharedCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sharedCatalog = config('catalog.export_path');
        $this->target = sys_get_temp_dir().'/catalog-export-'.bin2hex(random_bytes(4));

        config(['catalog.path' => base_path('tests/Fixtures/catalog'), 'catalog.export_path' => $this->target]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->target);

        parent::tearDown();
    }

    #[Test]
    public function it_writes_one_yaml_file_per_data_file(): void
    {
        $this->artisan('catalog:export')->assertSuccessful();

        $files = collect(File::allFiles($this->target))
            ->map(fn ($file) => $file->getRelativePathname())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'categories.yaml',
            'equipment.yaml',
            'exercises/cardio.yaml',
            'exercises/strength.yaml',
            'programs.yaml',
            'retired.yaml',
            'workout-templates/cardio.yaml',
            'workout-templates/strength.yaml',
        ], $files);
    }

    #[Test]
    public function it_writes_enum_values_and_keeps_the_set_shorthand(): void
    {
        $this->artisan('catalog:export')->assertSuccessful();

        $equipment = Yaml::parseFile("{$this->target}/equipment.yaml");
        $templates = Yaml::parseFile("{$this->target}/workout-templates/cardio.yaml");
        $programs = Yaml::parseFile("{$this->target}/programs.yaml");

        $this->assertSame('kilograms', $equipment[0]['difficulty_unit']);
        $this->assertSame(
            ['exercise' => 'easy-ride', 'sets' => 2, 'effort' => 600, 'difficulty' => 2],
            $templates[0]['activities'][1],
        );
        $this->assertSame(['weekday' => 'monday', 'template' => 'full-body'], $programs[0]['schedule'][0]);
        $this->assertSame(['slug' => 'old-rack', 'names' => ['Old Rack']], Yaml::parseFile("{$this->target}/retired.yaml")['equipment'][0]);
    }

    #[Test]
    public function the_check_passes_on_a_fresh_export_and_ignores_comments(): void
    {
        $this->artisan('catalog:export')->assertSuccessful();
        File::prepend("{$this->target}/programs.yaml", "# A comment the export does not write.\n");

        $this->artisan('catalog:export --check')
            ->expectsOutputToContain('The YAML catalog matches the PHP data files.')
            ->assertSuccessful();
    }

    #[Test]
    public function the_check_fails_when_a_value_differs(): void
    {
        $this->artisan('catalog:export')->assertSuccessful();
        File::put(
            "{$this->target}/exercises/strength.yaml",
            str_replace('rest_time_seconds: 120', 'rest_time_seconds: 90', File::get("{$this->target}/exercises/strength.yaml")),
        );

        $this->artisan('catalog:export --check')
            ->expectsOutputToContain('exercises/strength.yaml: differs from exercises/strength.php.')
            ->assertFailed();
    }

    #[Test]
    public function the_check_fails_when_a_file_is_missing_or_extra(): void
    {
        $this->artisan('catalog:export')->assertSuccessful();
        File::delete("{$this->target}/categories.yaml");
        File::put("{$this->target}/exercises/yoga.yaml", "[]\n");

        $this->artisan('catalog:export --check')
            ->expectsOutputToContain('categories.yaml: missing (exported from categories.php).')
            ->expectsOutputToContain('exercises/yoga.yaml: has no PHP data file.')
            ->assertFailed();
    }

    #[Test]
    public function it_keeps_existing_files_unless_forced(): void
    {
        File::ensureDirectoryExists($this->target);
        File::put("{$this->target}/categories.yaml", "# kept\n");

        $this->artisan('catalog:export')
            ->expectsOutputToContain('kept categories.yaml (exists; --force overwrites it)')
            ->assertSuccessful();
        $this->assertSame("# kept\n", File::get("{$this->target}/categories.yaml"));

        $this->artisan('catalog:export --force')->assertSuccessful();
        $this->assertSame('chest', Yaml::parseFile("{$this->target}/categories.yaml")[0]['slug']);
    }

    #[Test]
    public function the_shipped_yaml_catalog_matches_the_php_data_files(): void
    {
        $this->assertDirectoryExists($this->sharedCatalog);

        $this->assertSame([], (new CatalogExporter(database_path('catalog'), $this->sharedCatalog))->check());
    }
}
