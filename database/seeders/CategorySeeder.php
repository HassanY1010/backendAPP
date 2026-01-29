<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks to allow truncation
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Category::truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $categories = [
            [
                'title' => 'السيارات والمركبات',
                'icon' => 'directions_car_rounded',
                'color' => '#1E88E5',
                'sub' => [
                    [
                        'title' => 'سيارات للبيع',
                        'sub' => [
                            ['title' => 'تويوتا', 'sub' => [['title' => 'كامري'], ['title' => 'هايلوكس'], ['title' => 'لاندكروزر'], ['title' => 'شاص'], ['title' => 'برادو'], ['title' => 'كورولا'], ['title' => 'يارس']]],
                            ['title' => 'نيسان', 'sub' => [['title' => 'باترول'], ['title' => 'فتك'], ['title' => 'ددسن'], ['title' => 'صني']]],
                            ['title' => 'لكزس'],
                            ['title' => 'هيونداي / كيا'],
                            ['title' => 'صالون'],
                            ['title' => 'سيارات ديزل'],
                            ['title' => 'سيارات بنزين'],
                            ['title' => 'سيارات مستخدم أول / وكاله'],
                            ['title' => 'سيارات للإيجار'],
                            ['title' => 'سيارات تشليح / سكراب'],
                            ['title' => 'لوحات مميزة'],
                        ]
                    ],
                    ['title' => 'دراجات نارية', 'sub' => [['title' => 'دباب'], ['title' => 'سكوتر']]],
                    ['title' => 'شاحنات ومعدات ثقيلة', 'sub' => [['title' => 'قلاب'], ['title' => 'بوكات'], ['title' => 'شيولات'], ['title' => 'بلدوزر']]],
                    ['title' => 'قوارب وصنادل'],
                    ['title' => 'باصات ونقل جماعي'],
                    ['title' => 'زينة سيارات واكسسوارات'],
                    ['title' => 'قطع غيار سيارات'],
                ]
            ],
            [
                'title' => 'العقارات',
                'icon' => 'home_work_rounded',
                'color' => '#43A047',
                'sub' => [
                    [
                        'title' => 'للبيع',
                        'sub' => [
                            ['title' => 'أراضي', 'sub' => [['title' => 'تجاري'], ['title' => 'سكني'], ['title' => 'زراعي']]],
                            ['title' => 'بيوت'],
                            ['title' => 'فلل'],
                            ['title' => 'شقق تمليك'],
                            ['title' => 'محلات'],
                            ['title' => 'استراحات'],
                            ['title' => 'مزارع وطسوح'],
                        ]
                    ],
                    [
                        'title' => 'للإيجار',
                        'sub' => [
                            ['title' => 'شقق'],
                            ['title' => 'غرف عزاب'],
                            ['title' => 'فلل'],
                            ['title' => 'محلات'],
                            ['title' => 'مكاتب'],
                            ['title' => 'مخازن'],
                        ]
                    ],
                    ['title' => 'عقارات بصك'],
                    ['title' => 'عقارات بدون صك'],
                    ['title' => 'عقارات في القرى والمناطق الريفية'],
                ]
            ],
            [
                'title' => 'الأجهزة الإلكترونية',
                'icon' => 'devices_other_rounded',
                'color' => '#FB8C00',
                'sub' => [
                    [
                        'title' => 'جوالات',
                        'sub' => [['title' => 'آيفون'], ['title' => 'سامسونج'], ['title' => 'هواوي'], ['title' => 'شاومي']]
                    ],
                    ['title' => 'لابتوبات'],
                    ['title' => 'كمبيوترات مكتبية'],
                    ['title' => 'شاشات'],
                    [
                        'title' => 'أجهزة ألعاب',
                        'sub' => [['title' => 'PS5 – PS4'], ['title' => 'Xbox'], ['title' => 'Nintendo']]
                    ],
                    ['title' => 'كاميرات مراقبة'],
                    ['title' => 'راوترات وباقات إنترنت'],
                    ['title' => 'طابعات'],
                    ['title' => 'اكسسوارات'],
                ]
            ],
            [
                'title' => 'الأثاث والمفروشات',
                'icon' => 'chair_rounded',
                'color' => '#8E24AA',
                'sub' => [
                    ['title' => 'مجالس عربية'],
                    ['title' => 'صالات وكنب'],
                    ['title' => 'غرف نوم'],
                    ['title' => 'غرف أطفال'],
                    ['title' => 'طاولات'],
                    ['title' => 'مطابخ'],
                    ['title' => 'دواليب'],
                    ['title' => 'سجاد'],
                    ['title' => 'ستائر'],
                    ['title' => 'ديكور منزلي'],
                    ['title' => 'أثاث مكتبي'],
                    ['title' => 'أثاث مستخدم'],
                ]
            ],
            [
                'title' => 'الموضة والأزياء',
                'icon' => 'checkroom_rounded',
                'color' => '#E53935',
                'sub' => [
                    ['title' => 'ثياب رجالية'],
                    ['title' => 'عبايات'],
                    ['title' => 'فساتين'],
                    ['title' => 'ملابس أطفال'],
                    ['title' => 'أحذية'],
                    ['title' => 'ساعات'],
                    ['title' => 'نظارات'],
                    ['title' => 'عطور'],
                    ['title' => 'شنط'],
                ]
            ],
            [
                'title' => 'الأجهزة المنزلية',
                'icon' => 'kitchen_rounded',
                'color' => '#00ACC1',
                'sub' => [
                    ['title' => 'ثلاجات'],
                    ['title' => 'غسالات'],
                    [
                        'title' => 'مكيفات',
                        'sub' => [['title' => 'مكيف شباك'], ['title' => 'مكيف سبلت']]
                    ],
                    ['title' => 'أفران'],
                    ['title' => 'ميكرويف'],
                    ['title' => 'مراوح'],
                    ['title' => 'سخانات ماء'],
                    ['title' => 'مكاين قهوة'],
                    ['title' => 'أدوات مطبخ'],
                    ['title' => 'أجهزة تنقية الماء'],
                ]
            ],
            [
                'title' => 'حيوانات وطيور',
                'icon' => 'pets_rounded',
                'color' => '#795548',
                'sub' => [
                    ['title' => 'قطط'],
                    ['title' => 'كلاب'],
                    ['title' => 'أغنام'],
                    ['title' => 'معزا'],
                    ['title' => 'أبقار'],
                    ['title' => 'جمال'],
                    ['title' => 'خيول'],
                    ['title' => 'طيور زينة'],
                    ['title' => 'حمام'],
                    ['title' => 'دواجن'],
                    ['title' => 'أسماك'],
                    ['title' => 'مستلزمات حيوانات'],
                ]
            ],
            [
                'title' => 'هوايات وألعاب',
                'icon' => 'sports_esports_rounded',
                'color' => '#3949AB',
                'sub' => [
                    ['title' => 'دراجات هوائية'],
                    ['title' => 'أجهزة رياضية'],
                    ['title' => 'أدوات رحلات'],
                    ['title' => 'خيام وبر وبرّية'],
                    ['title' => 'كتب'],
                    ['title' => 'آلات موسيقية'],
                    ['title' => 'ألعاب أطفال'],
                    ['title' => 'أدوات صيد'],
                ]
            ],
            [
                'title' => 'معدات وأدوات',
                'icon' => 'construction_rounded',
                'color' => '#546E7A',
                'sub' => [
                    ['title' => 'معدات بناء'],
                    ['title' => 'معدات زراعية'],
                    ['title' => 'معدات ورش'],
                    ['title' => 'مكائن لحام'],
                    ['title' => 'مولدات كهرباء'],
                    ['title' => 'ضواغط هواء'],
                    ['title' => 'معدات طبية'],
                    ['title' => 'مكائن وخطوط إنتاج'],
                    ['title' => 'أدوات نجارة'],
                ]
            ],
            [
                'title' => 'مستلزمات شخصية',
                'icon' => 'brush_rounded',
                'color' => '#D81B60',
                'sub' => [
                    ['title' => 'عطور'],
                    ['title' => 'كريمات'],
                    ['title' => 'منتجات عناية'],
                    ['title' => 'بخاخات'],
                    ['title' => 'عدسات'],
                    ['title' => 'أدوات حلاقة'],
                ]
            ],
            [
                'title' => 'مستلزمات أطفال',
                'icon' => 'child_care_rounded',
                'color' => '#F06292',
                'sub' => [
                    ['title' => 'عربيات أطفال'],
                    ['title' => 'سرير طفل'],
                    ['title' => 'ألعاب'],
                    ['title' => 'ملابس'],
                    ['title' => 'أدوات تغذية'],
                    ['title' => 'كراسي أطفال'],
                ]
            ],
        ];

        $this->insertCategories($categories);
    }

    private function insertCategories(array $categories, ?int $parentId = null, ?string $parentColor = null)
    {
        foreach ($categories as $items) {
            $category = Category::create([
                'title' => $items['title'],
                'slug' => Str::slug($items['title']) . '-' . Str::random(6), // Ensure unique slug
                'parent_id' => $parentId,
                'icon' => $items['icon'] ?? null,
                'color' => $items['color'] ?? $parentColor ?? '#64748B', // Use parent color or default
                'is_active' => true,
            ]);

            if (isset($items['sub'])) {
                $this->insertCategories($items['sub'], $category->id, $category->color);
            }
        }
    }
}
