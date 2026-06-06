<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CategorySeeder extends Seeder
{
    private const IMAGES = [
        'vehicles' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=900&q=80',
        'toyota' => 'https://images.unsplash.com/photo-1621007947382-bb3c3994e3fb?auto=format&fit=crop&w=900&q=80',
        'car_type' => 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=900&q=80',
        'trucks' => 'https://images.unsplash.com/photo-1506306460327-3164753b74c7?auto=format&fit=crop&w=900&q=80',
        'motorcycles' => 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=900&q=80',
        'bicycles' => 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=900&q=80',
        'parts' => 'https://images.unsplash.com/photo-1487754180451-c456f719a1fc?auto=format&fit=crop&w=900&q=80',
        'real_estate' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=900&q=80',
        'land' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=900&q=80',
        'villa' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80',
        'apartment' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80',
        'commercial' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=80',
        'electronics' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?auto=format&fit=crop&w=900&q=80',
        'phones' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80',
        'computers' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=80',
        'gaming' => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?auto=format&fit=crop&w=900&q=80',
        'smart_devices' => 'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?auto=format&fit=crop&w=900&q=80',
        'furniture' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=80',
        'home_appliances' => 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&w=900&q=80',
        'decor' => 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=900&q=80',
        'fashion' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=900&q=80',
        'men_fashion' => 'https://images.unsplash.com/photo-1516257984-b1b4d707412e?auto=format&fit=crop&w=900&q=80',
        'women_fashion' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=900&q=80',
        'kids_fashion' => 'https://images.unsplash.com/photo-1503919545889-aef636e10ad4?auto=format&fit=crop&w=900&q=80',
        'jobs_services' => 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=900&q=80',
        'jobs' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=80',
        'services' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=80',
        'animals' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&q=80',
        'pets' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&w=900&q=80',
        'livestock' => 'https://images.unsplash.com/photo-1516467508483-a7212febe31a?auto=format&fit=crop&w=900&q=80',
        'animal_supplies' => 'https://images.unsplash.com/photo-1586973848955-2c7202e3f9e4?auto=format&fit=crop&w=900&q=80',
        'hobbies' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=900&q=80',
        'sports' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=900&q=80',
        'photography' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80',
        'music' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?auto=format&fit=crop&w=900&q=80',
        'books' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=900&q=80',
        'education' => 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?auto=format&fit=crop&w=900&q=80',
        'family' => 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=900&q=80',
        'baby' => 'https://images.unsplash.com/photo-1555252333-9f8e92e65df9?auto=format&fit=crop&w=900&q=80',
        'agriculture' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=900&q=80',
        'plants' => 'https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?auto=format&fit=crop&w=900&q=80',
        'industrial' => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=900&q=80',
        'heavy_equipment' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=900&q=80',
        'workshop' => 'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=900&q=80',
        'other' => 'https://images.unsplash.com/photo-1520640023173-50a135e35804?auto=format&fit=crop&w=900&q=80',
    ];

    public function run(): void
    {
        $this->seedTree($this->categories());

        Cache::forget('category_tree:v2');
        Cache::forget('category_tree');
    }

    private function seedTree(array $categories, ?Category $parent = null, ?string $parentColor = null, ?string $parentImage = null): void
    {
        foreach ($categories as $index => $item) {
            $image = $item['image'] ?? $this->imageForTitle($item['title'], $parentImage);
            $color = $item['color'] ?? $parentColor ?? '#64748B';

            $category = Category::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'parent_id' => $parent?->id,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'icon' => $item['icon'] ?? $parent?->icon ?? 'category_rounded',
                    'image' => $image,
                    'color' => $color,
                    'is_active' => $item['is_active'] ?? true,
                    'sort_order' => $item['sort_order'] ?? (($index + 1) * 10),
                ]
            );

            if (!empty($item['children'])) {
                $this->seedTree($item['children'], $category, $color, $image);
            }
        }
    }

    private function categories(): array
    {
        return [
            [
                'title' => 'السيارات والمركبات',
                'slug' => 'vehicles',
                'icon' => 'directions_car_rounded',
                'color' => '#2563EB',
                'image' => $this->img('vehicles'),
                'children' => [
                    ['title' => 'سيارات', 'slug' => 'vehicles-cars', 'image' => $this->img('vehicles'), 'children' => $this->vehicleBrands()],
                    ['title' => 'تصنيف حسب نوع السيارة', 'slug' => 'vehicles-car-types', 'image' => $this->img('car_type'), 'children' => [
                        ['title' => 'سيدان', 'slug' => 'vehicles-car-types-sedan'],
                        ['title' => 'SUV', 'slug' => 'vehicles-car-types-suv'],
                        ['title' => 'بيك أب', 'slug' => 'vehicles-car-types-pickup'],
                        ['title' => 'شاص', 'slug' => 'vehicles-car-types-chassis'],
                        ['title' => 'دفع رباعي', 'slug' => 'vehicles-car-types-4x4'],
                        ['title' => 'رياضية', 'slug' => 'vehicles-car-types-sports'],
                        ['title' => 'فاخرة', 'slug' => 'vehicles-car-types-luxury'],
                        ['title' => 'كهربائية', 'slug' => 'vehicles-car-types-electric'],
                        ['title' => 'هجينة', 'slug' => 'vehicles-car-types-hybrid'],
                    ]],
                    ['title' => 'شاحنات ومعدات نقل', 'slug' => 'vehicles-trucks-transport', 'image' => $this->img('trucks'), 'children' => [
                        ['title' => 'شاحنات صغيرة', 'slug' => 'vehicles-trucks-small'],
                        ['title' => 'شاحنات كبيرة', 'slug' => 'vehicles-trucks-large'],
                        ['title' => 'قلابات', 'slug' => 'vehicles-trucks-dump'],
                        ['title' => 'برادات', 'slug' => 'vehicles-trucks-refrigerated'],
                        ['title' => 'صهاريج', 'slug' => 'vehicles-trucks-tankers'],
                    ]],
                    ['title' => 'دراجات', 'slug' => 'vehicles-bikes', 'image' => $this->img('motorcycles'), 'children' => [
                        ['title' => 'دراجات نارية', 'slug' => 'vehicles-bikes-motorcycles'],
                        ['title' => 'سكوترات', 'slug' => 'vehicles-bikes-scooters'],
                        ['title' => 'دراجات هوائية', 'slug' => 'vehicles-bikes-bicycles', 'image' => $this->img('bicycles')],
                    ]],
                    ['title' => 'قطع غيار وإكسسوارات', 'slug' => 'vehicles-parts-accessories', 'image' => $this->img('parts'), 'children' => [
                        ['title' => 'محركات', 'slug' => 'vehicles-parts-engines'],
                        ['title' => 'قير', 'slug' => 'vehicles-parts-gearboxes'],
                        ['title' => 'كفرات', 'slug' => 'vehicles-parts-tires'],
                        ['title' => 'بطاريات', 'slug' => 'vehicles-parts-batteries'],
                        ['title' => 'زيوت', 'slug' => 'vehicles-parts-oils'],
                        ['title' => 'جنوط', 'slug' => 'vehicles-parts-rims'],
                        ['title' => 'أنظمة صوت', 'slug' => 'vehicles-parts-audio'],
                    ]],
                ],
            ],
            [
                'title' => 'العقارات',
                'slug' => 'real-estate',
                'icon' => 'home_work_rounded',
                'color' => '#16A34A',
                'image' => $this->img('real_estate'),
                'children' => [
                    ['title' => 'للبيع', 'slug' => 'real-estate-sale', 'children' => [
                        ['title' => 'أراضي', 'slug' => 'real-estate-sale-land', 'image' => $this->img('land')],
                        ['title' => 'فلل', 'slug' => 'real-estate-sale-villas', 'image' => $this->img('villa')],
                        ['title' => 'شقق', 'slug' => 'real-estate-sale-apartments', 'image' => $this->img('apartment')],
                        ['title' => 'عمائر', 'slug' => 'real-estate-sale-buildings', 'image' => $this->img('commercial')],
                        ['title' => 'مزارع', 'slug' => 'real-estate-sale-farms', 'image' => $this->img('agriculture')],
                        ['title' => 'استراحات', 'slug' => 'real-estate-sale-rest-houses'],
                        ['title' => 'محلات تجارية', 'slug' => 'real-estate-sale-shops', 'image' => $this->img('commercial')],
                    ]],
                    ['title' => 'للإيجار', 'slug' => 'real-estate-rent', 'children' => [
                        ['title' => 'شقق', 'slug' => 'real-estate-rent-apartments', 'image' => $this->img('apartment')],
                        ['title' => 'فلل', 'slug' => 'real-estate-rent-villas', 'image' => $this->img('villa')],
                        ['title' => 'مكاتب', 'slug' => 'real-estate-rent-offices', 'image' => $this->img('commercial')],
                        ['title' => 'محلات', 'slug' => 'real-estate-rent-shops', 'image' => $this->img('commercial')],
                        ['title' => 'مستودعات', 'slug' => 'real-estate-rent-warehouses', 'image' => $this->img('commercial')],
                    ]],
                    ['title' => 'الاستثمار العقاري', 'slug' => 'real-estate-investment', 'children' => [
                        ['title' => 'مشاريع عقارية', 'slug' => 'real-estate-investment-projects'],
                        ['title' => 'أراضٍ استثمارية', 'slug' => 'real-estate-investment-land', 'image' => $this->img('land')],
                    ]],
                ],
            ],
            [
                'title' => 'الإلكترونيات',
                'slug' => 'electronics',
                'icon' => 'devices_other_rounded',
                'color' => '#EA580C',
                'image' => $this->img('electronics'),
                'children' => [
                    ['title' => 'الجوالات', 'slug' => 'electronics-phones', 'image' => $this->img('phones'), 'children' => [
                        ['title' => 'آيفون', 'slug' => 'electronics-phones-iphone'],
                        ['title' => 'سامسونج', 'slug' => 'electronics-phones-samsung'],
                        ['title' => 'هواوي', 'slug' => 'electronics-phones-huawei'],
                        ['title' => 'شاومي', 'slug' => 'electronics-phones-xiaomi'],
                        ['title' => 'أوبو', 'slug' => 'electronics-phones-oppo'],
                        ['title' => 'ريلمي', 'slug' => 'electronics-phones-realme'],
                        ['title' => 'أخرى', 'slug' => 'electronics-phones-other'],
                    ]],
                    ['title' => 'الكمبيوترات', 'slug' => 'electronics-computers', 'image' => $this->img('computers'), 'children' => [
                        ['title' => 'لابتوبات', 'slug' => 'electronics-computers-laptops'],
                        ['title' => 'أجهزة مكتبية', 'slug' => 'electronics-computers-desktops'],
                        ['title' => 'شاشات', 'slug' => 'electronics-computers-monitors'],
                        ['title' => 'طابعات', 'slug' => 'electronics-computers-printers'],
                    ]],
                    ['title' => 'الألعاب', 'slug' => 'electronics-gaming', 'image' => $this->img('gaming'), 'children' => [
                        ['title' => 'PlayStation', 'slug' => 'electronics-gaming-playstation'],
                        ['title' => 'Xbox', 'slug' => 'electronics-gaming-xbox'],
                        ['title' => 'Nintendo', 'slug' => 'electronics-gaming-nintendo'],
                        ['title' => 'ألعاب وملحقات', 'slug' => 'electronics-gaming-games-accessories'],
                    ]],
                    ['title' => 'الأجهزة الذكية', 'slug' => 'electronics-smart-devices', 'image' => $this->img('smart_devices'), 'children' => [
                        ['title' => 'ساعات ذكية', 'slug' => 'electronics-smart-watches'],
                        ['title' => 'سماعات', 'slug' => 'electronics-headphones'],
                        ['title' => 'كاميرات', 'slug' => 'electronics-cameras', 'image' => $this->img('photography')],
                    ]],
                ],
            ],
            [
                'title' => 'الأثاث والمنزل',
                'slug' => 'home-furniture',
                'icon' => 'chair_rounded',
                'color' => '#7C3AED',
                'image' => $this->img('furniture'),
                'children' => [
                    ['title' => 'أثاث المنزل', 'slug' => 'home-furniture-items', 'image' => $this->img('furniture'), 'children' => [
                        ['title' => 'غرف نوم', 'slug' => 'home-furniture-bedrooms'],
                        ['title' => 'مجالس', 'slug' => 'home-furniture-majlis'],
                        ['title' => 'كنب', 'slug' => 'home-furniture-sofas'],
                        ['title' => 'طاولات', 'slug' => 'home-furniture-tables'],
                        ['title' => 'خزائن', 'slug' => 'home-furniture-wardrobes'],
                    ]],
                    ['title' => 'الأجهزة المنزلية', 'slug' => 'home-appliances', 'icon' => 'kitchen_rounded', 'image' => $this->img('home_appliances'), 'children' => [
                        ['title' => 'ثلاجات', 'slug' => 'home-appliances-fridges'],
                        ['title' => 'غسالات', 'slug' => 'home-appliances-washers'],
                        ['title' => 'أفران', 'slug' => 'home-appliances-ovens'],
                        ['title' => 'مكيفات', 'slug' => 'home-appliances-ac'],
                    ]],
                    ['title' => 'الديكور', 'slug' => 'home-decor', 'image' => $this->img('decor'), 'children' => [
                        ['title' => 'سجاد', 'slug' => 'home-decor-rugs'],
                        ['title' => 'ستائر', 'slug' => 'home-decor-curtains'],
                        ['title' => 'إضاءة', 'slug' => 'home-decor-lighting'],
                        ['title' => 'لوحات', 'slug' => 'home-decor-art'],
                    ]],
                ],
            ],
            [
                'title' => 'الأزياء والموضة',
                'slug' => 'fashion',
                'icon' => 'checkroom_rounded',
                'color' => '#DB2777',
                'image' => $this->img('fashion'),
                'children' => [
                    ['title' => 'رجال', 'slug' => 'fashion-men', 'image' => $this->img('men_fashion'), 'children' => [
                        ['title' => 'ملابس', 'slug' => 'fashion-men-clothes'],
                        ['title' => 'أحذية', 'slug' => 'fashion-men-shoes'],
                        ['title' => 'ساعات', 'slug' => 'fashion-men-watches'],
                        ['title' => 'عطور', 'slug' => 'fashion-men-perfumes'],
                    ]],
                    ['title' => 'نساء', 'slug' => 'fashion-women', 'image' => $this->img('women_fashion'), 'children' => [
                        ['title' => 'فساتين', 'slug' => 'fashion-women-dresses'],
                        ['title' => 'عبايات', 'slug' => 'fashion-women-abayas'],
                        ['title' => 'حقائب', 'slug' => 'fashion-women-bags'],
                        ['title' => 'إكسسوارات', 'slug' => 'fashion-women-accessories'],
                    ]],
                    ['title' => 'أطفال', 'slug' => 'fashion-kids', 'image' => $this->img('kids_fashion'), 'children' => [
                        ['title' => 'ملابس أطفال', 'slug' => 'fashion-kids-clothes'],
                        ['title' => 'أحذية أطفال', 'slug' => 'fashion-kids-shoes'],
                    ]],
                ],
            ],
            [
                'title' => 'الوظائف والخدمات',
                'slug' => 'jobs-services',
                'icon' => 'work_rounded',
                'color' => '#0F766E',
                'image' => $this->img('jobs_services'),
                'children' => [
                    ['title' => 'وظائف', 'slug' => 'jobs', 'image' => $this->img('jobs'), 'children' => [
                        ['title' => 'تقنية المعلومات', 'slug' => 'jobs-it'],
                        ['title' => 'الهندسة', 'slug' => 'jobs-engineering'],
                        ['title' => 'المحاسبة', 'slug' => 'jobs-accounting'],
                        ['title' => 'التسويق', 'slug' => 'jobs-marketing'],
                        ['title' => 'التعليم', 'slug' => 'jobs-education'],
                        ['title' => 'الطب', 'slug' => 'jobs-medical'],
                    ]],
                    ['title' => 'خدمات', 'slug' => 'services', 'image' => $this->img('services'), 'children' => [
                        ['title' => 'تصميم', 'slug' => 'services-design'],
                        ['title' => 'برمجة', 'slug' => 'services-programming'],
                        ['title' => 'ترجمة', 'slug' => 'services-translation'],
                        ['title' => 'نقل أثاث', 'slug' => 'services-furniture-moving'],
                        ['title' => 'تنظيف', 'slug' => 'services-cleaning'],
                        ['title' => 'صيانة', 'slug' => 'services-maintenance'],
                    ]],
                ],
            ],
            [
                'title' => 'الحيوانات',
                'slug' => 'animals',
                'icon' => 'pets_rounded',
                'color' => '#92400E',
                'image' => $this->img('animals'),
                'children' => [
                    ['title' => 'حيوانات أليفة', 'slug' => 'animals-pets', 'image' => $this->img('pets'), 'children' => [
                        ['title' => 'قطط', 'slug' => 'animals-pets-cats'],
                        ['title' => 'كلاب', 'slug' => 'animals-pets-dogs'],
                        ['title' => 'طيور', 'slug' => 'animals-pets-birds'],
                        ['title' => 'أسماك', 'slug' => 'animals-pets-fish'],
                    ]],
                    ['title' => 'مواشي', 'slug' => 'animals-livestock', 'image' => $this->img('livestock'), 'children' => [
                        ['title' => 'أغنام', 'slug' => 'animals-livestock-sheep'],
                        ['title' => 'إبل', 'slug' => 'animals-livestock-camels'],
                        ['title' => 'أبقار', 'slug' => 'animals-livestock-cows'],
                        ['title' => 'ماعز', 'slug' => 'animals-livestock-goats'],
                    ]],
                    ['title' => 'مستلزمات الحيوانات', 'slug' => 'animals-supplies', 'image' => $this->img('animal_supplies'), 'children' => [
                        ['title' => 'أعلاف', 'slug' => 'animals-supplies-feed'],
                        ['title' => 'أقفاص', 'slug' => 'animals-supplies-cages'],
                        ['title' => 'إكسسوارات', 'slug' => 'animals-supplies-accessories'],
                    ]],
                ],
            ],
            [
                'title' => 'الهوايات والترفيه',
                'slug' => 'hobbies-entertainment',
                'icon' => 'sports_esports_rounded',
                'color' => '#4F46E5',
                'image' => $this->img('hobbies'),
                'children' => [
                    ['title' => 'ألعاب', 'slug' => 'hobbies-games', 'image' => $this->img('gaming'), 'children' => [
                        ['title' => 'ألعاب إلكترونية', 'slug' => 'hobbies-games-electronic'],
                        ['title' => 'ألعاب أطفال', 'slug' => 'hobbies-games-kids'],
                        ['title' => 'ألعاب جماعية', 'slug' => 'hobbies-games-board'],
                    ]],
                    ['title' => 'رياضة', 'slug' => 'hobbies-sports', 'image' => $this->img('sports'), 'children' => [
                        ['title' => 'أجهزة رياضية', 'slug' => 'hobbies-sports-equipment'],
                        ['title' => 'دراجات', 'slug' => 'hobbies-sports-bicycles', 'image' => $this->img('bicycles')],
                        ['title' => 'مستلزمات رياضية', 'slug' => 'hobbies-sports-supplies'],
                    ]],
                    ['title' => 'هوايات', 'slug' => 'hobbies-personal', 'children' => [
                        ['title' => 'تصوير', 'slug' => 'hobbies-photography', 'image' => $this->img('photography')],
                        ['title' => 'رسم', 'slug' => 'hobbies-drawing', 'image' => $this->img('decor')],
                        ['title' => 'موسيقى', 'slug' => 'hobbies-music', 'image' => $this->img('music')],
                        ['title' => 'جمع المقتنيات', 'slug' => 'hobbies-collectibles', 'image' => $this->img('other')],
                    ]],
                ],
            ],
            [
                'title' => 'الكتب والتعليم',
                'slug' => 'books-education',
                'icon' => 'menu_book_rounded',
                'color' => '#B45309',
                'image' => $this->img('books'),
                'children' => [
                    ['title' => 'كتب', 'slug' => 'books', 'image' => $this->img('books'), 'children' => [
                        ['title' => 'دينية', 'slug' => 'books-religious'],
                        ['title' => 'علمية', 'slug' => 'books-science'],
                        ['title' => 'أدبية', 'slug' => 'books-literature'],
                        ['title' => 'جامعية', 'slug' => 'books-university'],
                    ]],
                    ['title' => 'تعليم', 'slug' => 'education', 'image' => $this->img('education'), 'children' => [
                        ['title' => 'دورات', 'slug' => 'education-courses'],
                        ['title' => 'دروس خصوصية', 'slug' => 'education-private-lessons'],
                        ['title' => 'مواد تعليمية', 'slug' => 'education-materials'],
                    ]],
                ],
            ],
            [
                'title' => 'الأسرة والطفل',
                'slug' => 'family-kids',
                'icon' => 'child_care_rounded',
                'color' => '#E11D48',
                'image' => $this->img('family'),
                'children' => [
                    ['title' => 'مستلزمات الأطفال', 'slug' => 'family-kids-supplies', 'image' => $this->img('baby'), 'children' => [
                        ['title' => 'عربات', 'slug' => 'family-kids-strollers'],
                        ['title' => 'مقاعد سيارات', 'slug' => 'family-kids-car-seats'],
                        ['title' => 'ألعاب أطفال', 'slug' => 'family-kids-toys'],
                        ['title' => 'ملابس أطفال', 'slug' => 'family-kids-clothes', 'image' => $this->img('kids_fashion')],
                    ]],
                    ['title' => 'الأمومة', 'slug' => 'family-motherhood', 'image' => $this->img('family'), 'children' => [
                        ['title' => 'مستلزمات الرضع', 'slug' => 'family-motherhood-baby-supplies'],
                        ['title' => 'أدوات الرعاية', 'slug' => 'family-motherhood-care-tools'],
                    ]],
                ],
            ],
            [
                'title' => 'الزراعة والحدائق',
                'slug' => 'agriculture-gardens',
                'icon' => 'agriculture_rounded',
                'color' => '#65A30D',
                'image' => $this->img('agriculture'),
                'children' => [
                    ['title' => 'معدات زراعية', 'slug' => 'agriculture-equipment', 'image' => $this->img('agriculture'), 'children' => [
                        ['title' => 'مضخات', 'slug' => 'agriculture-equipment-pumps'],
                        ['title' => 'أدوات زراعة', 'slug' => 'agriculture-equipment-tools'],
                        ['title' => 'مولدات', 'slug' => 'agriculture-equipment-generators'],
                    ]],
                    ['title' => 'نباتات', 'slug' => 'agriculture-plants', 'image' => $this->img('plants'), 'children' => [
                        ['title' => 'أشجار', 'slug' => 'agriculture-plants-trees'],
                        ['title' => 'شتلات', 'slug' => 'agriculture-plants-seedlings'],
                        ['title' => 'زهور', 'slug' => 'agriculture-plants-flowers'],
                    ]],
                ],
            ],
            [
                'title' => 'المعدات الصناعية',
                'slug' => 'industrial-equipment',
                'icon' => 'construction_rounded',
                'color' => '#475569',
                'image' => $this->img('industrial'),
                'children' => [
                    ['title' => 'معدات ثقيلة', 'slug' => 'industrial-heavy-equipment', 'image' => $this->img('heavy_equipment'), 'children' => [
                        ['title' => 'حفارات', 'slug' => 'industrial-heavy-excavators'],
                        ['title' => 'شيولات', 'slug' => 'industrial-heavy-loaders'],
                        ['title' => 'رافعات', 'slug' => 'industrial-heavy-cranes'],
                    ]],
                    ['title' => 'معدات ورش', 'slug' => 'industrial-workshop', 'image' => $this->img('workshop'), 'children' => [
                        ['title' => 'لحام', 'slug' => 'industrial-workshop-welding'],
                        ['title' => 'كمبروسرات', 'slug' => 'industrial-workshop-compressors'],
                        ['title' => 'أدوات صناعية', 'slug' => 'industrial-workshop-tools'],
                    ]],
                ],
            ],
            [
                'title' => 'أخرى',
                'slug' => 'other',
                'icon' => 'category_rounded',
                'color' => '#64748B',
                'image' => $this->img('other'),
                'sort_order' => 9999,
                'children' => [
                    ['title' => 'مقتنيات نادرة', 'slug' => 'other-rare-collectibles'],
                    ['title' => 'أشياء مجانية', 'slug' => 'other-free-items'],
                    ['title' => 'مفقودات وموجودات', 'slug' => 'other-lost-found'],
                    ['title' => 'متنوعات', 'slug' => 'other-misc'],
                ],
            ],
        ];
    }

    private function vehicleBrands(): array
    {
        return [
            ['title' => 'تويوتا', 'slug' => 'vehicles-cars-toyota', 'image' => $this->img('toyota')],
            ['title' => 'نيسان', 'slug' => 'vehicles-cars-nissan'],
            ['title' => 'هيونداي', 'slug' => 'vehicles-cars-hyundai'],
            ['title' => 'كيا', 'slug' => 'vehicles-cars-kia'],
            ['title' => 'هوندا', 'slug' => 'vehicles-cars-honda'],
            ['title' => 'فورد', 'slug' => 'vehicles-cars-ford'],
            ['title' => 'شيفروليه', 'slug' => 'vehicles-cars-chevrolet'],
            ['title' => 'جيب', 'slug' => 'vehicles-cars-jeep'],
            ['title' => 'لكزس', 'slug' => 'vehicles-cars-lexus'],
            ['title' => 'مرسيدس', 'slug' => 'vehicles-cars-mercedes'],
            ['title' => 'BMW', 'slug' => 'vehicles-cars-bmw'],
            ['title' => 'أودي', 'slug' => 'vehicles-cars-audi'],
            ['title' => 'MG', 'slug' => 'vehicles-cars-mg'],
            ['title' => 'جيلي', 'slug' => 'vehicles-cars-geely'],
            ['title' => 'شانجان', 'slug' => 'vehicles-cars-changan'],
            ['title' => 'هافال', 'slug' => 'vehicles-cars-haval'],
            ['title' => 'أخرى', 'slug' => 'vehicles-cars-other'],
        ];
    }

    private function imageForTitle(string $title, ?string $fallback): string
    {
        $matches = [
            ['terms' => ['سيارات', 'تويوتا', 'نيسان', 'هيونداي', 'كيا', 'هوندا', 'فورد', 'شيفروليه', 'جيب', 'لكزس', 'مرسيدس', 'BMW', 'أودي', 'MG', 'جيلي', 'شانجان', 'هافال', 'سيدان', 'SUV', 'بيك أب', 'شاص', 'دفع رباعي', 'رياضية', 'فاخرة', 'كهربائية', 'هجينة'], 'image' => $this->img('vehicles')],
            ['terms' => ['شاحنات', 'قلابات', 'برادات', 'صهاريج'], 'image' => $this->img('trucks')],
            ['terms' => ['دراجات نارية', 'سكوترات'], 'image' => $this->img('motorcycles')],
            ['terms' => ['دراجات هوائية', 'دراجات'], 'image' => $this->img('bicycles')],
            ['terms' => ['قطع غيار', 'محركات', 'قير', 'كفرات', 'بطاريات', 'زيوت', 'جنوط', 'أنظمة صوت'], 'image' => $this->img('parts')],
            ['terms' => ['أراضي', 'أراضٍ'], 'image' => $this->img('land')],
            ['terms' => ['فلل', 'استراحات'], 'image' => $this->img('villa')],
            ['terms' => ['شقق'], 'image' => $this->img('apartment')],
            ['terms' => ['عمائر', 'محلات', 'مكاتب', 'مستودعات', 'مشاريع'], 'image' => $this->img('commercial')],
            ['terms' => ['جوالات', 'آيفون', 'سامسونج', 'هواوي', 'شاومي', 'أوبو', 'ريلمي'], 'image' => $this->img('phones')],
            ['terms' => ['لابتوبات', 'أجهزة مكتبية', 'شاشات', 'طابعات', 'كمبيوتر'], 'image' => $this->img('computers')],
            ['terms' => ['PlayStation', 'Xbox', 'Nintendo', 'ألعاب إلكترونية', 'ألعاب وملحقات'], 'image' => $this->img('gaming')],
            ['terms' => ['ساعات ذكية', 'سماعات', 'كاميرات'], 'image' => $this->img('smart_devices')],
            ['terms' => ['غرف نوم', 'مجالس', 'كنب', 'طاولات', 'خزائن'], 'image' => $this->img('furniture')],
            ['terms' => ['ثلاجات', 'غسالات', 'أفران', 'مكيفات'], 'image' => $this->img('home_appliances')],
            ['terms' => ['سجاد', 'ستائر', 'إضاءة', 'لوحات', 'رسم'], 'image' => $this->img('decor')],
            ['terms' => ['رجال', 'أحذية', 'ساعات', 'عطور'], 'image' => $this->img('men_fashion')],
            ['terms' => ['نساء', 'فساتين', 'عبايات', 'حقائب', 'إكسسوارات'], 'image' => $this->img('women_fashion')],
            ['terms' => ['أطفال', 'ملابس أطفال', 'أحذية أطفال'], 'image' => $this->img('kids_fashion')],
            ['terms' => ['وظائف', 'تقنية المعلومات', 'الهندسة', 'المحاسبة', 'التسويق', 'التعليم', 'الطب'], 'image' => $this->img('jobs')],
            ['terms' => ['خدمات', 'تصميم', 'برمجة', 'ترجمة', 'نقل أثاث', 'تنظيف', 'صيانة'], 'image' => $this->img('services')],
            ['terms' => ['قطط', 'كلاب', 'طيور', 'أسماك'], 'image' => $this->img('pets')],
            ['terms' => ['أغنام', 'إبل', 'أبقار', 'ماعز', 'مواشي'], 'image' => $this->img('livestock')],
            ['terms' => ['أعلاف', 'أقفاص'], 'image' => $this->img('animal_supplies')],
            ['terms' => ['رياضة', 'أجهزة رياضية', 'مستلزمات رياضية'], 'image' => $this->img('sports')],
            ['terms' => ['تصوير'], 'image' => $this->img('photography')],
            ['terms' => ['موسيقى'], 'image' => $this->img('music')],
            ['terms' => ['كتب', 'دينية', 'علمية', 'أدبية', 'جامعية'], 'image' => $this->img('books')],
            ['terms' => ['دورات', 'دروس خصوصية', 'مواد تعليمية'], 'image' => $this->img('education')],
            ['terms' => ['عربات', 'مقاعد سيارات', 'مستلزمات الرضع', 'أدوات الرعاية'], 'image' => $this->img('baby')],
            ['terms' => ['معدات زراعية', 'مضخات', 'أدوات زراعة', 'مولدات'], 'image' => $this->img('agriculture')],
            ['terms' => ['نباتات', 'أشجار', 'شتلات', 'زهور'], 'image' => $this->img('plants')],
            ['terms' => ['حفارات', 'شيولات', 'رافعات', 'معدات ثقيلة'], 'image' => $this->img('heavy_equipment')],
            ['terms' => ['لحام', 'كمبروسرات', 'أدوات صناعية', 'معدات ورش'], 'image' => $this->img('workshop')],
        ];

        foreach ($matches as $match) {
            foreach ($match['terms'] as $term) {
                if (mb_stripos($title, $term) !== false) {
                    return $match['image'];
                }
            }
        }

        return $fallback ?? $this->img('other');
    }

    private function img(string $key): string
    {
        return self::IMAGES[$key];
    }
}
