<?php

namespace Database\Seeders;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Six demo courses with a three-section curriculum each, taught by a demo teacher.
 *
 * Safe to run repeatedly: courses are matched by slug, and a course that already has
 * a curriculum keeps it untouched.
 */
class CourseSeeder extends Seeder
{
    private const DEMO_VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    /**
     * @var array<int, array<string, mixed>>
     */
    private const COURSES = [
        [
            'title' => 'تطوير مواقع الويب من الصفر',
            'slug' => 'تطوير-مواقع-الويب-من-الصفر',
            'category' => 'تطوير-الويب',
            'level' => CourseLevel::Beginner,
            'price' => 149, 'sale_price' => 99, 'is_free' => false, 'is_featured' => true,
            'short' => 'تعلّم HTML وCSS وJavaScript وابنِ أول موقع احترافي متجاوب.',
            'sections' => ['أساسيات HTML', 'تنسيق الصفحات بـ CSS', 'التفاعل مع JavaScript'],
        ],
        [
            'title' => 'بناء تطبيقات الجوال بـ Flutter',
            'slug' => 'بناء-تطبيقات-الجوال-بـ-flutter',
            'category' => 'تطوير-تطبيقات-الجوال',
            'level' => CourseLevel::Intermediate,
            'price' => 199, 'sale_price' => null, 'is_free' => false, 'is_featured' => false,
            'short' => 'طوّر تطبيقات Android وiOS بشيفرة واحدة باستخدام Flutter وDart.',
            'sections' => ['مقدمة إلى Dart', 'واجهات Flutter', 'نشر التطبيق'],
        ],
        [
            'title' => 'أساسيات الموشن جرافيك في After Effects',
            'slug' => 'أساسيات-الموشن-جرافيك',
            'category' => 'الموشن-جرافيك',
            'level' => CourseLevel::Beginner,
            'price' => 120, 'sale_price' => 79, 'is_free' => false, 'is_featured' => true,
            'short' => 'حرّك النصوص والأشكال وأنشئ فيديوهات موشن جرافيك احترافية.',
            'sections' => ['التعرف على الواجهة', 'تحريك النصوص', 'مشروع تطبيقي'],
        ],
        [
            'title' => 'مدخل إلى التصميم الجرافيكي',
            'slug' => 'مدخل-إلى-التصميم-الجرافيكي',
            'category' => 'التصميم-الجرافيكي',
            'level' => CourseLevel::All,
            'price' => 0, 'sale_price' => null, 'is_free' => true, 'is_featured' => false,
            'short' => 'مبادئ التصميم والألوان والخطوط لكل من يبدأ رحلته في التصميم.',
            'sections' => ['مبادئ التصميم', 'نظرية الألوان', 'الخطوط والطباعة'],
        ],
        [
            'title' => 'التسويق الرقمي والإعلانات الممولة',
            'slug' => 'التسويق-الرقمي-والإعلانات-الممولة',
            'category' => 'التسويق-الرقمي',
            'level' => CourseLevel::Intermediate,
            'price' => 99, 'sale_price' => null, 'is_free' => false, 'is_featured' => false,
            'short' => 'خطط لحملاتك الإعلانية وحلّل نتائجها على منصات التواصل.',
            'sections' => ['أساسيات التسويق الرقمي', 'إعلانات منصات التواصل', 'قياس النتائج'],
        ],
        [
            'title' => 'المحادثة باللغة الإنجليزية للمبتدئين',
            'slug' => 'المحادثة-بالإنجليزية-للمبتدئين',
            'category' => 'اللغة-الإنجليزية',
            'level' => CourseLevel::Beginner,
            'price' => 0, 'sale_price' => null, 'is_free' => true, 'is_featured' => true,
            'short' => 'عبارات ومواقف يومية لتتحدث الإنجليزية بثقة.',
            'sections' => ['التعارف والتحيات', 'في السوق والمطعم', 'السفر والمواصلات'],
        ],
    ];

    public function run(): void
    {
        if (! CourseCategory::query()->exists()) {
            $this->call(CourseCategorySeeder::class);
        }

        $teacher = $this->teacher();

        foreach (self::COURSES as $definition) {
            $category = CourseCategory::query()->where('slug', $definition['category'])->first()
                ?? CourseCategory::query()->firstOrFail();

            $course = Course::withTrashed()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'title' => $definition['title'],
                    'course_category_id' => $category->id,
                    'instructor_id' => $teacher->id,
                    'subtitle' => $definition['short'],
                    'short_description' => $definition['short'],
                    'description' => '<p>'.$definition['short'].'</p><p>دورة عملية مقسّمة إلى دروس قصيرة مع تطبيقات بعد كل قسم.</p>',
                    'level' => $definition['level'],
                    'language' => 'ar',
                    'is_free' => $definition['is_free'],
                    'price' => $definition['price'],
                    'sale_price' => $definition['sale_price'],
                    'currency' => 'USD',
                    'is_featured' => $definition['is_featured'],
                    'has_certificate' => ! $definition['is_free'],
                    'learning_outcomes' => ['فهم الأساسيات بعمق', 'تطبيق عملي على مشاريع حقيقية', 'بناء ملف أعمال'],
                    'requirements' => ['جهاز كمبيوتر واتصال بالإنترنت', 'الرغبة في التعلّم'],
                    'target_audience' => ['المبتدئون في المجال', 'من يريد تطوير مهاراته'],
                    'meta_title' => $definition['title'],
                ],
            );

            if ($course->trashed()) {
                $course->restore();
            }

            if (! $course->sections()->exists()) {
                $this->seedCurriculum($course, $definition['sections']);
            }

            // DatabaseSeeder runs WithoutModelEvents, so the hooks that keep these in sync don't fire.
            $course->refreshStats();
            $course->update([
                'status' => CourseStatus::Published,
                'published_at' => $course->published_at ?? now(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $sectionTitles
     */
    private function seedCurriculum(Course $course, array $sectionTitles): void
    {
        foreach ($sectionTitles as $sectionIndex => $sectionTitle) {
            $section = $course->sections()->create(['title' => $sectionTitle, 'order' => $sectionIndex + 1]);

            $lessons = [
                ['title' => 'مقدمة: '.$sectionTitle, 'type' => LessonType::Video, 'duration' => 240],
                ['title' => 'شرح عملي', 'type' => LessonType::Video, 'duration' => 720],
                ['title' => 'ملخص القسم', 'type' => LessonType::Text, 'duration' => 180],
            ];
            if ($sectionIndex === count($sectionTitles) - 1) {
                $lessons[] = ['title' => 'ملفات التمرين', 'type' => LessonType::File, 'duration' => 0];
            }

            foreach ($lessons as $lessonIndex => $lesson) {
                $section->lessons()->create([
                    'course_id' => $course->id,
                    'title' => $lesson['title'],
                    'type' => $lesson['type'],
                    'video_source' => $lesson['type'] === LessonType::Video ? VideoSource::Youtube : null,
                    'video' => $lesson['type'] === LessonType::Video ? self::DEMO_VIDEO : null,
                    'content' => $lesson['type'] === LessonType::Text ? '<p>ملخص لأهم النقاط في هذا القسم.</p>' : null,
                    'attachment' => $lesson['type'] === LessonType::File ? 'https://example.com/files/exercise.zip' : null,
                    'duration_seconds' => $lesson['duration'],
                    // The very first lesson of each course is a free preview.
                    'is_preview' => $sectionIndex === 0 && $lessonIndex === 0,
                    'is_published' => true,
                    'order' => $lessonIndex + 1,
                ]);
            }
        }
    }

    /**
     * The demo instructor. Accounts are admins or students only; this one is a
     * student, so it can sign in to the public site without panel access.
     */
    private function teacher(): User
    {
        Role::findOrCreate('student', 'web');

        $teacher = User::query()->firstOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name' => 'أحمد المدرّس',
                'name_ar' => 'أحمد المدرّس',
                'name_en' => 'Ahmad Teacher',
                'phone' => '0500000001',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (! $teacher->hasRole('student')) {
            $teacher->syncRoles(['student']);
        }

        return $teacher;
    }
}
