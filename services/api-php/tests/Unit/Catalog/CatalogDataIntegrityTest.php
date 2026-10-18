<?php

namespace Tests\Unit\Catalog;

use App\Services\Catalog\CatalogLoader;
use App\Services\Catalog\CatalogValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the shipped catalog, the repository's catalog/ that both backends
 * apply, with the PHP validator (the Go suite checks it with its own).
 */
class CatalogDataIntegrityTest extends TestCase
{
    #[Test]
    public function the_shipped_catalog_is_valid(): void
    {
        $catalog = $this->app->make(CatalogLoader::class)->load();

        $violations = $this->app->make(CatalogValidator::class)->validate($catalog);

        $this->assertSame([], $violations, "The catalog data files are invalid:\n".implode("\n", $violations));
    }

    #[Test]
    public function the_shipped_catalog_defines_every_record_type(): void
    {
        $catalog = $this->app->make(CatalogLoader::class)->load();

        foreach (CatalogLoader::TYPES as $type) {
            $this->assertNotEmpty($catalog[$type], "The catalog has no {$type}.");
        }
    }
}
