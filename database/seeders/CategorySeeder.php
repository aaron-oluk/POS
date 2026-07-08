<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Coffee', 'Tea', 'Pastries', 'Sandwiches', 'Desserts', 'Drinks', 'Merchandise'] as $name) {
            Category::create(['name' => $name]);
        }
    }
}
