<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and equipment.',
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'Supplies and materials used in office environments.',
            ],
            [
                'name' => 'Furniture',
                'description' => 'Office and workplace furniture.',
            ],
            [
                'name' => 'Networking',
                'description' => 'Networking equipment and infrastructure.',
            ],
            [
                'name' => 'Accessories',
                'description' => 'Computer and electronic accessories.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'slug' => Str::slug($category['name']),
                    'description' => $category['description'],
                ]
            );
        }
    }
}