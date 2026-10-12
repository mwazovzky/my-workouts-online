<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog Data Path
    |--------------------------------------------------------------------------
    |
    | Directory holding the catalog data files (equipment, categories,
    | exercises, workout templates, programs) that `catalog:sync` applies
    | to the database.
    |
    */

    'path' => database_path('catalog'),

    /*
    |--------------------------------------------------------------------------
    | Shared YAML Catalog
    |--------------------------------------------------------------------------
    |
    | The repository's catalog/ directory: the same data as YAML, read by the
    | Go API. `catalog:export --check` keeps it equal to the PHP data files
    | until the PHP API reads the YAML itself. The dev container mounts it
    | elsewhere and sets CATALOG_EXPORT_PATH.
    |
    */

    'export_path' => env('CATALOG_EXPORT_PATH', base_path('../../catalog')),

];
