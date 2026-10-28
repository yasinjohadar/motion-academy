<?php

namespace Database\Seeders;

use App\Enums\HeroSlideVisual;
use App\Models\HeroSlide;
use App\Services\Frontend\HeroSliderSettings;
use Illuminate\Database\Seeder;

/**
 * The four slides the hero shipped with, now as editable rows, plus the
 * slider's default behaviour. Matched on title, so re-running refreshes them
 * without duplicating; slides added from the panel are left alone.
 */
class HeroSlideSeeder extends Seeder
{
    public function run(HeroSliderSettings $settings): void
    {
        foreach ($this->slides() as $order => $slide) {
            HeroSlide::query()->updateOrCreate(
                ['title' => $slide['title']],
                [...$slide, 'is_active' => true, 'sort_order' => $order + 1],
            );
        }

        $settings->seedDefaults();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slides(): array
    {
        return [
            [
                'badge_text' => 'We turn ideas into life',
                'badge_icon' => 'fas fa-play',
                'title' => "نحوّل أفكارك\nإلى حركة وحياة",
                'title_highlight' => 'أفكارك',
                'description' => 'أكاديمية متخصصة في التحريك الرقمي والتدريب على برامجه، نبني بيئة إبداعية تساعدك على صناعة محتوى بصري احترافي.',
                'primary_button_label' => 'تصفح الدورات',
                'primary_button_url' => route('courses', absolute: false),
                'primary_button_icon' => 'fas fa-arrow-left',
                'secondary_button_label' => 'من نحن',
                'secondary_button_url' => route('about', absolute: false),
                'secondary_button_icon' => 'fas fa-play-circle',
                'bullet_label' => 'الأكاديمية',
                'visual' => HeroSlideVisual::Editor,
                'show_stats' => true,
            ],
            [
                'badge_text' => 'الموشن جرافيك',
                'badge_icon' => 'fas fa-bezier-curve',
                'title' => 'كل إطار يحكي قصة',
                'title_highlight' => 'يحكي قصة',
                'description' => 'تعلّم الإطارات المفتاحية ومنحنيات السرعة والتوقيت، وحوّل التصاميم الثابتة إلى حركة سلسة تشد المشاهد.',
                'primary_button_label' => 'استكشف المجالات',
                'primary_button_url' => route('categories', absolute: false),
                'primary_button_icon' => 'fas fa-arrow-left',
                'secondary_button_label' => 'كل الدورات',
                'secondary_button_url' => route('courses', absolute: false),
                'secondary_button_icon' => 'fas fa-layer-group',
                'bullet_label' => 'الموشن',
                'visual' => HeroSlideVisual::Curve,
                'show_stats' => false,
            ],
            [
                'badge_text' => 'صناعة المحتوى البصري',
                'badge_icon' => 'fas fa-photo-video',
                'title' => 'دورات عملية خطوة بخطوة',
                'title_highlight' => 'خطوة بخطوة',
                'description' => 'منهج مقسّم إلى دروس قصيرة وتطبيقات بعد كل قسم، لتبني ملف أعمالك وأنت تتعلّم.',
                'primary_button_label' => 'شاهد الدورات',
                'primary_button_url' => route('courses', absolute: false),
                'primary_button_icon' => 'fas fa-arrow-left',
                'secondary_button_label' => 'المدونة',
                'secondary_button_url' => route('blog.index', absolute: false),
                'secondary_button_icon' => 'fas fa-pen-nib',
                'bullet_label' => 'الدورات',
                'visual' => HeroSlideVisual::Courses,
                'show_stats' => false,
            ],
            [
                'badge_text' => 'ابدأ الآن',
                'badge_icon' => 'fas fa-unlock',
                'title' => 'جرّب قبل أن تبدأ',
                'title_highlight' => 'تبدأ',
                'description' => 'الدرس الأول من كل دورة متاح للمعاينة مجاناً — شاهده وقرّر بنفسك أين تبدأ رحلتك.',
                'primary_button_label' => 'ابدأ بدرس مجاني',
                'primary_button_url' => route('courses', absolute: false),
                'primary_button_icon' => 'fas fa-arrow-left',
                'secondary_button_label' => 'أنشئ حسابك',
                'secondary_button_url' => route('register', absolute: false),
                'secondary_button_icon' => 'fas fa-user-plus',
                'bullet_label' => 'ابدأ مجاناً',
                'visual' => HeroSlideVisual::Preview,
                'show_stats' => false,
            ],
        ];
    }
}
