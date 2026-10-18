<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog Data Path
    |--------------------------------------------------------------------------
    |
    | Directory holding the shared YAML catalog (equipment, categories,
    | exercises, workout templates, programs) that `catalog:sync` applies to
    | the database: the repository's catalog/, also read by the Go API. The
    | dev container mounts it elsewhere and the api image copies it to
    | /var/www/catalog; both set CATALOG_PATH.
    |
    */

    'path' => env('CATALOG_PATH', base_path('../../catalog')),

];
