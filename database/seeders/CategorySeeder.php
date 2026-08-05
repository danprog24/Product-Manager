<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Category::updateOrCreate([
            'name' => 'Electronics',
        ]);

        Category::updateOrCreate([
            'name' => 'Furniture',
        ]);

        Category::updateOrCreate([
            'name' => 'Accessories',
        ]);

        Category::updateOrCreate([
            'name' => 'Laptops',
        ]);

        Category::updateOrCreate([
            'name' => 'Books'
        ]);
    }
}