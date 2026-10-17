<?php

namespace Database\Seeders;

use App\Models\CourseCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds 10 starter course categories: 4 roots and 6 children.
 * Safe to run repeatedly — rows are matched by slug and updated in place.
 */
class CourseCategorySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, slug: string, description: string, icon: string, color: string, is_featured: bool, children: array<int, array{name: string, slug: string, description: string, icon: string}>}>
     */
    private const CATEGORIES = [
        [
            'name' => 'البرمجة والتطوير',
            'slug' => 'البرمجة-والتطوير',
            'description' => 'تعلّم لغات البرمجة وبناء التطبيقات من الصفر حتى الاحتراف.',
            'icon' => 'ri-code-s-slash-line',
            'color' => '#1a83c1',
            'is_featured' => true,
            'children' => [
                ['name' => 'تطوير الويب', 'slug' => 'تطوير-الويب', 'description' => 'HTML وCSS وJavaScript وأطر العمل الحديثة.', 'icon' => 'ri-global-line'],
                ['name' => 'تطوير تطبيقات الجوال', 'slug' => 'تطوير-تطبيقات-الجوال', 'description' => 'بناء تطبيقات Android وiOS.', 'icon' => 'ri-smartphone-line'],
            ],
        ],
        [
            'name' => 'التصميم والموشن جرافيك',
            'slug' => 'التصميم-والموشن-جرافيك',
            'description' => 'من أساسيات التصميم إلى تحريك الرسوميات والمونتاج.',
            'icon' => 'ri-palette-line',
            'color' => '#33a4da',
            'is_featured' => true,
            'children' => [
                ['name' => 'التصميم الجرافيكي', 'slug' => 'التصميم-الجرافيكي', 'description' => 'الهوية البصرية والطباعة والسوشيال ميديا.', 'icon' => 'ri-artboard-line'],
                ['name' => 'الموشن جرافيك', 'slug' => 'الموشن-جرافيك', 'description' => 'تحريك الرسوميات باستخدام After Effects.', 'icon' => 'ri-movie-2-line'],
            ],
        ],
        [
            'name' => 'الأعمال والتسويق',
            'slug' => 'الأعمال-والتسويق',
            'description' => 'ريادة الأعمال والتسويق الرقمي وإدارة المشاريع.',
            'icon' => 'ri-line-chart-line',
            'color' => '#02ad8f',
            'is_featured' => false,
            'children' => [
                ['name' => 'التسويق الرقمي', 'slug' => 'التسويق-الرقمي', 'description' => 'الإعلانات الممولة وتحسين محركات البحث.', 'icon' => 'ri-megaphone-line'],
            ],
        ],
        [
            'name' => 'اللغات',
            'slug' => 'اللغات',
            'description' => 'تعلّم اللغات الأجنبية للمحادثة والعمل والدراسة.',
            'icon' => 'ri-translate-2',
            'color' => '#45bec4',
            'is_featured' => false,
            'children' => [
                ['name' => 'اللغة الإنجليزية', 'slug' => 'اللغة-الإنجليزية', 'description' => 'من المستوى المبتدئ حتى المتقدم.', 'icon' => 'ri-book-open-line'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $rootOrder => $root) {
            $parent = $this->upsert([
                'name' => $root['name'],
                'slug' => $root['slug'],
                'description' => $root['description'],
                'icon' => $root['icon'],
                'color' => $root['color'],
                'is_featured' => $root['is_featured'],
                'parent_id' => null,
                'order' => $rootOrder + 1,
            ]);

            foreach ($root['children'] as $childOrder => $child) {
                $this->upsert([
                    ...$child,
                    'color' => $root['color'],
                    'is_featured' => false,
                    'parent_id' => $parent->id,
                    'order' => $childOrder + 1,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(array $attributes): CourseCategory
    {
        $category = CourseCategory::withTrashed()->updateOrCreate(
            ['slug' => $attributes['slug']],
            [...$attributes, 'is_active' => true, 'meta_title' => $attributes['name']],
        );

        if ($category->trashed()) {
            $category->restore();
        }

        return $category;
    }
}
