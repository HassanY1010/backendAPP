<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class OtherCategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(
            ['slug' => 'other'],
            [
                'parent_id' => null,
                'title' => 'أخرى',
                'description' => 'قسم خاص للسلع والطلبات التي لا تندرج تحت الأقسام الأخرى',
                'icon' => 'category_rounded',
                'image' => 'https://images.unsplash.com/photo-1520640023173-50a135e35804?auto=format&fit=crop&w=900&q=80',
                'color' => '#64748B',
                'is_active' => true,
                'sort_order' => 9999,
            ]
        );

        $this->command?->info('"Other" category is ready.');
    }
}
