<?php

namespace App\Console\Commands;

use App\Exceptions\InvalidCatalogException;
use App\Services\Catalog\CatalogSyncReport;
use App\Services\Catalog\CatalogSyncServiceInterface;
use Illuminate\Console\Command;

class CatalogSyncCommand extends Command
{
    protected $signature = 'catalog:sync
        {--dry-run : Report what would change without writing anything}
        {--strict : Write nothing and fail when the sync raises warnings}';

    protected $description = 'Apply the catalog data files (equipment, categories, exercises, workout templates, programs) to the database';

    public function handle(CatalogSyncServiceInterface $catalogSync): int
    {
        try {
            $report = $catalogSync->sync(
                dryRun: (bool) $this->option('dry-run'),
                strict: (bool) $this->option('strict'),
            );
        } catch (InvalidCatalogException $exception) {
            $this->error('The catalog data is invalid. Nothing was written.');

            foreach ($exception->violations as $violation) {
                $this->line("  - {$violation}");
            }

            return self::FAILURE;
        }

        $this->printReport($report);

        if ($report->dryRun) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if (! $report->committed()) {
            $this->error('Strict mode: the sync raised warnings. Nothing was written.');

            return self::FAILURE;
        }

        $this->info('Catalog synced.');

        return self::SUCCESS;
    }

    private function printReport(CatalogSyncReport $report): void
    {
        $rows = [];

        foreach ($report->counts() as $type => $counts) {
            $rows[] = [$type, ...array_values($counts)];
        }

        $this->table(['Type', ...array_map('ucfirst', CatalogSyncReport::OUTCOMES)], $rows);

        foreach ($report->changes() as $change) {
            $this->line("  {$change}");
        }

        foreach ($report->warnings() as $warning) {
            $this->warn("  Warning: {$warning}");
        }
    }
}
