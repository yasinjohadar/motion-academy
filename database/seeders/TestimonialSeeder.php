<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use App\Services\Frontend\TestimonialSettings;
use Illuminate\Database\Seeder;

/**
 * The three testimonials the home page shipped with plus three more, so the
 * slider layout and the home page limit have something to work with. Matched on
 * name, so re-running refreshes them without duplicating.
 */
class TestimonialSeeder extends Seeder
{
    public function run(TestimonialSettings $settings): void
    {
        foreach ($this->testimonials() as $order => $testimonial) {
            Testimonial::query()->updateOrCreate(
                ['name' => $testimonial['name']],
                [...$testimonial, 'is_active' => true, 'sort_order' => $order + 1],
            );
        }

        $settings->seedDefaults();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function testimonials(): array
    {
        return [
            [
                'name' => 'أحمد محمود',
                'role' => 'Front-end Developer',
                'content' => 'بفضل الكورسات هنا، استطعت تغيير مساري المهني والعمل كمطور واجهات أمامية في شركة عالمية بوقت قياسي.',
                'rating' => 5,
                'icon' => 'fas fa-user',
                'color' => '#02ad8f',
                'is_featured' => true,
            ],
            [
                'name' => 'سارة خليل',
                'role' => 'UI/UX Designer',
                'content' => 'منصة رائعة، المحتوى مشروح بطريقة مبسطة وعملية. الكورسات يتم تحديثها باستمرار لتطابق متطلبات السوق.',
                'rating' => 4.5,
                'icon' => 'fas fa-user-graduate',
                'color' => '#33a4da',
                'is_featured' => true,
            ],
            [
                'name' => 'محمد العلي',
                'role' => 'Data Analyst',
                'content' => 'المشاريع العملية والدعم المستمر من المدربين ساعدوني على بناء portfolio قوي والحصول على وظيفة خلال شهرين فقط.',
                'rating' => 5,
                'icon' => 'fas fa-laptop-code',
                'color' => '#1a83c1',
                'is_featured' => true,
            ],
            [
                'name' => 'ليان الحسن',
                'role' => 'Motion Designer',
                'company' => 'استوديو حرّك',
                'content' => 'تعلّمت منحنيات السرعة والتوقيت بشكل لم أجده في أي مكان آخر، وصارت حركاتي أنعم وأكثر احترافية.',
                'rating' => 5,
                'icon' => 'fas fa-bezier-curve',
                'color' => '#45bec4',
                'is_featured' => false,
            ],
            [
                'name' => 'عمر يوسف',
                'role' => 'Video Editor',
                'content' => 'الدروس قصيرة ومركّزة، وملفات التمرين جعلتني أطبّق كل فكرة مباشرة بعد مشاهدتها.',
                'rating' => 4.5,
                'icon' => 'fas fa-film',
                'color' => '#02ad8f',
                'is_featured' => false,
            ],
            [
                'name' => 'نور الشامي',
                'role' => 'Content Creator',
                'content' => 'أصبحت أصمم الإنترو والفواصل لقناتي بنفسي، ووفّرت الكثير من الوقت والتكلفة.',
                'rating' => 5,
                'icon' => 'fas fa-photo-video',
                'color' => '#33a4da',
                'is_featured' => false,
            ],
        ];
    }
}
