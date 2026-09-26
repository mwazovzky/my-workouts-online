<?php

namespace App\Services\Admin;

use App\Models\Category;

interface CategoryServiceInterface
{
    /**
     * @param  array{translations: array<string, array<string, ?string>>}  $data
     */
    public function create(array $data): Category;

    /**
     * @param  array{translations: array<string, array<string, ?string>>}  $data
     */
    public function update(Category $category, array $data): Category;

    public function delete(Category $category): void;
}
