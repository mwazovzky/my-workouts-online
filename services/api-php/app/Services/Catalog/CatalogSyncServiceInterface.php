<?php

namespace App\Services\Catalog;

use App\Exceptions\InvalidCatalogException;

interface CatalogSyncServiceInterface
{
    /**
     * Apply the catalog data files to the database.
     *
     * Records are matched by slug; rows without a slug are adopted by their
     * English name. Nothing is written when the data files are invalid, on a
     * dry run, or in strict mode when the sync raised warnings.
     *
     * @throws InvalidCatalogException
     */
    public function sync(bool $dryRun = false, bool $strict = false): CatalogSyncReport;
}
