<?php

namespace Tests\Feature\Seeders;

use App\Enums\HeroSlideVisual;
use App\Models\HeroSlide;
use App\Services\Frontend\HeroSliderSettings;
use Database\Seeders\HeroSlideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroSlideSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_four_original_slides_in_order(): void
    {
        $this->seed(HeroSlideSeeder::class);

        $slides = HeroSlide::query()->showing()->get();

        $this->assertCount(4, $slides);
        $this->assertSame(
            [HeroSlideVisual::Editor, HeroSlideVisual::Curve, HeroSlideVisual::Courses, HeroSlideVisual::Preview],
            $slides->pluck('visual')->all(),
        );
        $this->assertSame(['الأكاديمية', 'الموشن', 'الدورات', 'ابدأ مجاناً'], $slides->pluck('bullet_label')->all());
        $this->assertTrue($slides->first()->show_stats);
    }

    public function test_it_is_idempotent_and_keeps_saved_settings(): void
    {
        $this->seed(HeroSlideSeeder::class);
        app(HeroSliderSettings::class)->save(['autoplay_delay' => 9000]);

        $this->seed(HeroSlideSeeder::class);

        $this->assertSame(4, HeroSlide::query()->count());
        $this->assertSame(9000, app(HeroSliderSettings::class)->all()['autoplay_delay']);
    }
}
