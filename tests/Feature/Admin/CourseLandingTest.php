<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseLanding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin.courses.list', 'admin.courses.edit', 'admin.courses.edit-any'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return [
            'is_published' => true,
            'tagline' => 'احترف التحريك في 30 يوماً',
            'description' => '<h2>لماذا هذه الدورة؟</h2><p>لأنها عملية.</p>',
            'videos' => [
                ['title' => 'المقدمة', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ['title' => '', 'url' => ''],
            ],
            'goals' => ['تحريك الشعارات', '', 'بناء ملف أعمال'],
            'features' => [['title' => 'شهادة', 'text' => 'بعد إتمام الدورة']],
            'testimonials' => [
                ['name' => 'ريم', 'role' => 'مصممة', 'text' => 'رائعة', 'video_url' => ''],
                ['name' => 'عمر', 'role' => '', 'text' => '', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'],
            ],
            'faqs' => [
                ['question' => 'متى تبدأ الدورة؟', 'answer' => 'فور التسجيل.'],
                ['question' => '', 'answer' => ''],
            ],
            'social_links' => [
                ['platform' => 'whatsapp', 'url' => 'https://wa.me/966500000000'],
                ['platform' => 'instagram', 'url' => ''],
            ],
            'show_price' => true,
            'cta_label' => 'احجز مقعدك',
            'cta_url' => '',
            'meta_title' => '',
            'meta_description' => '',
            'allow_indexing' => false,
            ...$overrides,
        ];
    }

    public function test_a_new_landing_page_starts_from_the_course_outcomes(): void
    {
        $course = Course::factory()->create(['learning_outcomes' => ['الهدف الأول', 'الهدف الثاني']]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.landing.edit', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Landing')
                ->where('landing.exists', false)
                ->where('landing.is_published', false)
                ->where('landing.goals', ['الهدف الأول', 'الهدف الثاني'])
                ->where('course.landing_url', route('courses.landing', $course->slug))
                ->has('platforms'));
    }

    public function test_admin_can_save_the_landing_page_and_blank_rows_are_dropped(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.landing.update', $course), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $landing = $course->landing()->sole();
        $this->assertTrue($landing->is_published);
        $this->assertSame('احترف التحريك في 30 يوماً', $landing->tagline);
        $this->assertCount(1, $landing->videos);
        $this->assertSame(['تحريك الشعارات', 'بناء ملف أعمال'], $landing->goals);
        $this->assertSame([['platform' => 'whatsapp', 'url' => 'https://wa.me/966500000000']], $landing->social_links);
        $this->assertCount(2, $landing->testimonials);
        $this->assertSame('احجز مقعدك', $landing->cta_label);
        $this->assertSame([['question' => 'متى تبدأ الدورة؟', 'answer' => 'فور التسجيل.']], $landing->faqs);
    }

    public function test_saving_again_updates_the_same_landing_page(): void
    {
        $landing = CourseLanding::factory()->complete()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.landing.update', $landing->course_id), $this->validPayload([
                'videos' => [],
                'features' => [],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CourseLanding::query()->count());
        $landing->refresh();
        $this->assertSame([], $landing->videos);
        $this->assertSame([], $landing->features);
    }

    public function test_invalid_landing_input_is_rejected(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.landing.update', $course), $this->validPayload([
                'videos' => [['title' => 'ليس يوتيوب', 'url' => 'https://vimeo.com/123']],
                'features' => [['title' => '', 'text' => 'شرح بلا عنوان']],
                'testimonials' => [
                    ['name' => 'سارة', 'role' => 'طالبة', 'text' => '', 'video_url' => ''],
                    ['name' => 'خالد', 'role' => '', 'text' => '', 'video_url' => 'https://example.com/video.mp4'],
                ],
                'social_links' => [['platform' => 'facebook', 'url' => 'javascript:alert(1)']],
                'faqs' => [['question' => 'سؤال بلا جواب', 'answer' => '']],
                'cta_url' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors([
                'videos.0.url',
                'features.0.title',
                'testimonials.0.text',
                'testimonials.1.video_url',
                'social_links.0.url',
                'faqs.0.answer',
                'cta_url',
            ]);

        $this->assertNull($course->landing()->first());
    }

    public function test_an_instructor_cannot_edit_another_instructors_landing_page(): void
    {
        $instructor = User::factory()->staff()->create();
        $instructor->givePermissionTo(['admin.courses.list', 'admin.courses.edit']);
        $someoneElsesCourse = Course::factory()->create();
        $ownCourse = Course::factory()->create(['instructor_id' => $instructor->id]);

        // Policy denials come back as an inline flash error (see bootstrap/app.php), not a 403 page.
        $this->actingAs($instructor)
            ->get(route('admin.courses.landing.edit', $someoneElsesCourse))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->actingAs($instructor)
            ->put(route('admin.courses.landing.update', $someoneElsesCourse), $this->validPayload())
            ->assertSessionHas('error');
        $this->assertNull($someoneElsesCourse->landing()->first());

        $this->actingAs($instructor)
            ->put(route('admin.courses.landing.update', $ownCourse), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertNotNull($ownCourse->landing()->first());
    }

    public function test_course_list_flags_each_landing_page_state(): void
    {
        $withoutLanding = Course::factory()->create(['title' => 'بلا صفحة']);
        $published = CourseLanding::factory()->create()->course;
        $draft = CourseLanding::factory()->draft()->create()->course;

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.index'))
            ->assertInertia(function (Assert $page) use ($withoutLanding, $published, $draft) {
                $flags = collect($page->toArray()['props']['courses']['data'])->pluck('landing_published', 'id');

                $this->assertNull($flags[$withoutLanding->id]);
                $this->assertTrue($flags[$published->id]);
                $this->assertFalse($flags[$draft->id]);
            });
    }

    public function test_user_without_permission_cannot_open_the_editor(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->panelUserWithoutPermissions())
            ->get(route('admin.courses.landing.edit', $course))
            ->assertForbidden();
    }
}
