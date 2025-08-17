<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Chest',
            'Shoulders',
            'Triceps',
            'Back',
            'Biceps',
            'Legs',
            'Abs',
        ];

        foreach ($categories as $name) {
            Category::create(['name' => $name]);
        }
    }
}
