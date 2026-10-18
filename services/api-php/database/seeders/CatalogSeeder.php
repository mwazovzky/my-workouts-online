<?php

namespace Database\Seeders;

use App\Services\Catalog\CatalogSyncServiceInterface;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Seed the catalog from the shared YAML catalog (config catalog.path).
     * Safe to run repeatedly and in every environment.
     */
    public function run(CatalogSyncServiceInterface $catalogSync): void
    {
        $report = $catalogSync->sync();

        foreach ($report->warnings() as $warning) {
            $this->command?->warn($warning);
        }
    }
}
