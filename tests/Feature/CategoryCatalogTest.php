<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_catalog_seed_creates_public_tree_with_images(): void
    {
        $this->seed(CategorySeeder::class);

        $response = $this->getJson('/api/v1/categories')
            ->assertOk();

        $categories = collect($response->json('data'));
        $vehicles = $categories->firstWhere('slug', 'vehicles');
        $realEstate = $categories->firstWhere('slug', 'real-estate');
        $other = $categories->firstWhere('slug', 'other');

        $this->assertGreaterThanOrEqual(13, $categories->count());
        $this->assertSame('السيارات والمركبات', $vehicles['title']);
        $this->assertNotEmpty($vehicles['image']);
        $this->assertSame('العقارات', $realEstate['title']);
        $this->assertSame('أخرى', $other['title']);

        $cars = collect($vehicles['children'])->firstWhere('slug', 'vehicles-cars');
        $toyota = collect($cars['children'])->firstWhere('slug', 'vehicles-cars-toyota');

        $this->assertSame('سيارات', $cars['title']);
        $this->assertSame('تويوتا', $toyota['title']);
        $this->assertNotEmpty($toyota['image']);
    }
}
