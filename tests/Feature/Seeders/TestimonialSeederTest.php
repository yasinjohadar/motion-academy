<?php

namespace Tests\Feature\Seeders;

use App\Models\Testimonial;
use App\Services\Frontend\TestimonialSettings;
use Database\Seeders\TestimonialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_six_testimonials_with_the_original_three_first(): void
    {
        $this->seed(TestimonialSeeder::class);

        $this->assertSame(6, Testimonial::query()->active()->count());
        $this->assertSame(
            ['أحمد محمود', 'سارة خليل', 'محمد العلي'],
            Testimonial::query()->orderBy('sort_order')->take(3)->pluck('name')->all(),
        );
        $this->assertSame(3, Testimonial::query()->where('is_featured', true)->count());
        $this->assertSame(3, app(TestimonialSettings::class)->all()['limit']);
    }

    public function test_it_is_idempotent_and_keeps_saved_settings(): void
    {
        $this->seed(TestimonialSeeder::class);
        app(TestimonialSettings::class)->save(['limit' => 6]);

        $this->seed(TestimonialSeeder::class);

        $this->assertSame(6, Testimonial::query()->count());
        $this->assertSame(6, app(TestimonialSettings::class)->all()['limit']);
    }
}
