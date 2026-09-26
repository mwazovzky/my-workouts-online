<?php

namespace App\Services\Admin;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryService implements CategoryServiceInterface
{
    public function create(array $data): Category
    {
        return DB::transaction(fn () => Category::createWithTranslations($data['translations']));
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->updateTranslations($data['translations']);

            return $category;
        });
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
