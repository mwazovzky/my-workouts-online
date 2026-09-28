<?php

/*
 * Records removed from the catalog. Removing a record from its data file
 * deletes nothing; it must be listed here. Exercises are soft-deleted.
 * Other types are deleted only when nothing references them.
 *
 * 'names' are the English names a row without a slug is recognised by.
 *
 * Example: ['slug' => 'bench', 'names' => ['Bench']]
 */
return [
    'equipment' => [],
    'categories' => [],
    'exercises' => [],
    'workout_templates' => [],
    'programs' => [],
];
