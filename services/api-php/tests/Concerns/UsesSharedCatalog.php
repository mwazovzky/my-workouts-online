<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The fixtures under the shared catalog/testdata/, which the Go tests use too:
 * `valid/` is a small valid catalog, and each `invalid/<rule>/` holds only the
 * files that differ from it.
 */
trait UsesSharedCatalog
{
    /**
     * @var array<int, string>
     */
    private array $catalogCopies = [];

    protected function tearDownUsesSharedCatalog(): void
    {
        foreach ($this->catalogCopies as $directory) {
            File::deleteDirectory($directory);
        }
    }

    protected function validCatalogPath(): string
    {
        return config('catalog.path').'/testdata/valid';
    }

    protected function invalidCatalogsPath(): string
    {
        return config('catalog.path').'/testdata/invalid';
    }

    /**
     * A temporary copy of the valid catalog with the given files replaced
     * (relative path => YAML text, or null to delete) and the files of an
     * overlay directory copied over it.
     *
     * @param  array<string, string|null>  $files
     */
    protected function catalogCopy(array $files = [], ?string $overlay = null): string
    {
        $directory = sys_get_temp_dir().'/catalog-'.Str::random(12);
        File::copyDirectory($this->validCatalogPath(), $directory);
        $this->catalogCopies[] = $directory;

        if ($overlay !== null) {
            File::copyDirectory($overlay, $directory);
        }

        foreach ($files as $file => $yaml) {
            if ($yaml === null) {
                File::delete("{$directory}/{$file}");

                continue;
            }

            File::put("{$directory}/{$file}", $yaml);
        }

        return $directory;
    }
}
