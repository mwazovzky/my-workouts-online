<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogExporter;
use Illuminate\Console\Command;

class CatalogExportCommand extends Command
{
    protected $signature = 'catalog:export
        {--check : Compare the YAML files with the PHP data files and fail on any difference}
        {--force : Overwrite existing YAML files (drops comments added by hand)}';

    protected $description = 'Export the catalog data files to the shared YAML catalog the Go API reads';

    public function handle(CatalogExporter $exporter): int
    {
        if ($this->option('check')) {
            $differences = $exporter->check();

            if ($differences !== []) {
                $this->error('The YAML catalog differs from the PHP data files. Change both, or run catalog:export --force and restore the comments.');

                foreach ($differences as $difference) {
                    $this->line("  - {$difference}");
                }

                return self::FAILURE;
            }

            $this->info('The YAML catalog matches the PHP data files.');

            return self::SUCCESS;
        }

        foreach ($exporter->export((bool) $this->option('force')) as $file => $written) {
            $this->line($written ? "  wrote {$file}" : "  kept {$file} (exists; --force overwrites it)");
        }

        return self::SUCCESS;
    }
}
