<?php

namespace Tests\Unit\Catalog;

use App\Services\Catalog\CatalogLoader;
use App\Services\Catalog\CatalogValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the catalog data files shipped in database/catalog.
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
