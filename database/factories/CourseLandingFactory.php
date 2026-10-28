<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLanding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLanding>
 */
class CourseLandingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->published(),
            'is_published' => true,
            'tagline' => fake()->sentence(8),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'goals' => [fake()->sentence(5), fake()->sentence(5)],
            'show_price' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    /**
     * Every optional block filled in.
     */
    public function complete(): static
    {
        return $this->state(fn () => [
            'videos' => [['title' => 'مقدمة الدورة', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
            'features' => [['title' => 'شهادة إتمام', 'text' => 'تحصل عليها بعد إنهاء الدورة.']],
            'testimonials' => [
                ['name' => 'ريم', 'role' => 'مصممة', 'text' => 'دورة ممتازة.', 'video_url' => null],
                ['name' => 'عمر', 'role' => null, 'text' => null, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'],
            ],
            'faqs' => [
                ['question' => 'هل أحتاج خبرة سابقة؟', 'answer' => 'لا، الدورة تبدأ من الصفر.'],
                ['question' => 'هل أحصل على شهادة؟', 'answer' => 'نعم، بعد إتمام كل الدروس.'],
            ],
            'social_links' => [
                ['platform' => 'whatsapp', 'url' => 'https://wa.me/966500000000'],
                ['platform' => 'instagram', 'url' => 'https://instagram.com/motion'],
            ],
        ]);
    }
}
