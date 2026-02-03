<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class OtherCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if "Other" category already exists
        $existingCategory = Category::where('slug', 'other')->first();

        if (!$existingCategory) {
            Category::create([
                'parent_id' => null,
                'title' => 'سلعة أخرى',
                'slug' => 'other',
                'description' => 'قسم خاص للسلع التي لا تندرج تحت الأقسام الأخرى',
                'icon' => '📦', // Box emoji as icon
                'image' => null,
                'color' => '#6B7280', // Gray color to distinguish it
                'is_active' => true,
                'sort_order' => 9999, // High number to appear at the end
            ]);

            $this->command->info('✅ "Other Category" (سلعة أخرى) created successfully!');
        } else {
            $this->command->info('ℹ️  "Other Category" already exists.');
        }
    }
}
