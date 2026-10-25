<?php

namespace Tests\Feature\Catalog;

use App\Enums\DifficultyUnit;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\WorkoutTemplate;
use App\Services\Catalog\CatalogLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Runs the command against the shipped catalog (the repository's catalog/).
 */
class CatalogSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function shippedCatalog(): array
    {
        return $this->app->make(CatalogLoader::class)->load();
    }

    #[Test]
    public function it_syncs_the_shipped_catalog(): void
    {
        $catalog = $this->shippedCatalog();

        $this->artisan('catalog:sync')
            ->expectsOutputToContain('Catalog synced.')
            ->assertSuccessful();

        $this->assertDatabaseCount('equipment', count($catalog['equipment']));
        $this->assertDatabaseCount('categories', count($catalog['categories']));
        $this->assertDatabaseCount('exercises', count($catalog['exercises']));
        $this->assertDatabaseCount('workout_templates', count($catalog['workout_templates']));
        $this->assertDatabaseCount('programs', count($catalog['programs']));

        $this->assertSame(0, Exercise::whereNull('slug')->count());
        $this->assertSame(0, WorkoutTemplate::whereNull('slug')->count());
        $this->assertSame(0, Program::whereNull('slug')->count());
    }

    #[Test]
    public function it_can_run_repeatedly(): void
    {
        $catalog = $this->shippedCatalog();

        $this->artisan('catalog:sync')->assertSuccessful();
        $this->artisan('catalog:sync')->assertSuccessful();

        $this->assertDatabaseCount('exercises', count($catalog['exercises']));
        $this->assertDatabaseCount('programs', count($catalog['programs']));
    }

    #[Test]
    public function it_adopts_the_cycling_program_renamed_with_a_hyphen_in_production(): void
    {
        $existing = Program::createWithTranslations([
            'en' => ['name' => 'Cycling - Beginner (Week 1)', 'description' => 'Edited in the admin UI.'],
        ]);

        $this->artisan('catalog:sync')
            ->expectsOutputToContain('programs [cycling-beginner-week-1]: adopted')
            ->assertSuccessful();

        $this->assertDatabaseCount('programs', count($this->shippedCatalog()['programs']));
        $this->assertSame('cycling-beginner-week-1', $existing->fresh()->slug);
        $this->assertSame('Cycling — Beginner (Week 1)', $existing->fresh()->rawTranslation('name', 'en'));
        $this->assertSame(6, $existing->workoutTemplates()->count());
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        $this->artisan('catalog:sync', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->assertSuccessful();

        $this->assertDatabaseCount('equipment', 0);
        $this->assertDatabaseCount('programs', 0);
    }

    #[Test]
    public function it_fails_and_lists_the_violations_when_the_catalog_is_invalid(): void
    {
        config(['catalog.path' => config('catalog.path').'/testdata/missing']);

        $this->artisan('catalog:sync')
            ->expectsOutputToContain('The catalog data is invalid. Nothing was written.')
            ->expectsOutputToContain('does not exist')
            ->assertFailed();

        $this->assertDatabaseCount('equipment', 0);
    }

    #[Test]
    public function strict_mode_fails_when_the_sync_raises_warnings(): void
    {
        Equipment::createWithTranslations(['en' => ['name' => 'Barbell']], ['difficulty_unit' => DifficultyUnit::Kilograms]);
        Equipment::createWithTranslations(['en' => ['name' => 'Barbell']], ['difficulty_unit' => DifficultyUnit::Kilograms]);

        $this->artisan('catalog:sync', ['--strict' => true])
            ->expectsOutputToContain('Strict mode: the sync raised warnings. Nothing was written.')
            ->assertFailed();

        $this->assertDatabaseCount('equipment', 2);
        $this->assertSame(2, Equipment::whereNull('slug')->count());
    }

    #[Test]
    public function without_strict_mode_warnings_do_not_fail_the_sync(): void
    {
        Equipment::createWithTranslations(['en' => ['name' => 'Barbell']], ['difficulty_unit' => DifficultyUnit::Kilograms]);
        Equipment::createWithTranslations(['en' => ['name' => 'Barbell']], ['difficulty_unit' => DifficultyUnit::Kilograms]);

        $this->artisan('catalog:sync')
            ->expectsOutputToContain('2 rows match by name')
            ->assertSuccessful();

        $this->assertSame(1, Equipment::whereNull('slug')->count());
    }
}
