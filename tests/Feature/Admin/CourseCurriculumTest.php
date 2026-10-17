<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonAttachment;
use App\Models\CourseSection;
use App\Models\StorageDiskMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseCurriculumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin.courses.list', 'admin.courses.edit', 'admin.courses.edit-any'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        StorageDiskMapping::query()->where('disk_name', Course::VIDEO_DISK)->delete();
        Storage::fake('local');
        Storage::fake(config('storage.fallback_disk', 'public'));
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    protected function sectionWithCourse(): CourseSection
    {
        return CourseSection::factory()->create();
    }

    public function test_curriculum_page_lists_sections_and_lessons_in_order(): void
    {
        $course = Course::factory()->create();
        $second = CourseSection::factory()->create(['course_id' => $course->id, 'order' => 2, 'title' => 'ثانياً']);
        $first = CourseSection::factory()->create(['course_id' => $course->id, 'order' => 1, 'title' => 'أولاً']);
        CourseLesson::factory()->create(['course_section_id' => $first->id, 'order' => 2, 'title' => 'ب']);
        CourseLesson::factory()->create(['course_section_id' => $first->id, 'order' => 1, 'title' => 'أ']);
        CourseLesson::factory()->text()->create(['course_section_id' => $second->id]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.curriculum', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Curriculum')
                ->where('sections.0.title', 'أولاً')
                ->where('sections.0.lessons.0.title', 'أ')
                ->where('sections.0.lessons.1.title', 'ب')
                ->where('sections.1.lessons.0.type', 'text'));
    }

    public function test_section_crud_and_reorder(): void
    {
        $admin = $this->adminUser();
        $course = Course::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.courses.sections.store', $course), ['title' => 'المقدمة'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('admin.courses.sections.store', $course), ['title' => 'الأساسيات']);

        [$intro, $basics] = $course->sections()->get()->all();
        $this->assertSame(1, $intro->order);
        $this->assertSame(2, $basics->order);

        $this->actingAs($admin)
            ->put(route('admin.courses.sections.update', [$course, $intro]), ['title' => 'مقدمة الدورة'])
            ->assertSessionHasNoErrors();
        $this->assertSame('مقدمة الدورة', $intro->fresh()->title);

        $this->actingAs($admin)
            ->post(route('admin.courses.sections.reorder', $course), ['ids' => [$basics->id, $intro->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $basics->fresh()->order);
        $this->assertSame(2, $intro->fresh()->order);

        $this->actingAs($admin)->delete(route('admin.courses.sections.destroy', [$course, $intro]));
        $this->assertModelMissing($intro);
    }

    public function test_section_routes_are_scoped_to_their_course(): void
    {
        $course = Course::factory()->create();
        $foreignSection = $this->sectionWithCourse();

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.sections.update', [$course, $foreignSection]), ['title' => 'x'])
            ->assertNotFound();

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.sections.reorder', $course), ['ids' => [$foreignSection->id]])
            ->assertSessionHasErrors('ids');
    }

    public function test_add_youtube_lesson_and_course_stats_update(): void
    {
        $section = $this->sectionWithCourse();

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.lessons.store', [$section->course_id, $section]), [
                'title' => 'مقدمة',
                'type' => 'video',
                'video_source' => 'youtube',
                'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 300,
                'is_preview' => true,
            ])
            ->assertSessionHasNoErrors();

        $lesson = CourseLesson::query()->firstOrFail();
        $this->assertSame($section->course_id, $lesson->course_id);
        $this->assertSame(VideoSource::Youtube, $lesson->video_source);
        $this->assertTrue($lesson->is_preview);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $lesson->videoUrl());

        $course = $section->course->fresh();
        $this->assertSame(1, $course->lessons_count);
        $this->assertSame(300, $course->duration_seconds);
    }

    public function test_hidden_lessons_are_not_counted(): void
    {
        $section = $this->sectionWithCourse();
        CourseLesson::factory()->create(['course_section_id' => $section->id, 'duration_seconds' => 100]);
        CourseLesson::factory()->hidden()->create(['course_section_id' => $section->id, 'duration_seconds' => 900]);

        $course = $section->course->fresh();
        $this->assertSame(1, $course->lessons_count);
        $this->assertSame(100, $course->duration_seconds);
    }

    public function test_lesson_validation_depends_on_type_and_source(): void
    {
        $admin = $this->adminUser();
        $section = $this->sectionWithCourse();
        $url = route('admin.courses.lessons.store', [$section->course_id, $section]);

        $this->actingAs($admin)->post($url, ['title' => 'x', 'type' => 'video'])
            ->assertSessionHasErrors('video_source');
        $this->actingAs($admin)->post($url, ['title' => 'x', 'type' => 'video', 'video_source' => 'vimeo', 'video' => 'https://youtube.com/watch?v=abcdefg'])
            ->assertSessionHasErrors('video');
        $this->actingAs($admin)->post($url, ['title' => 'x', 'type' => 'video', 'video_source' => 'upload'])
            ->assertSessionHasErrors('video_file');
        $this->actingAs($admin)->post($url, ['title' => 'x', 'type' => 'text'])
            ->assertSessionHasErrors('content');
        $this->actingAs($admin)->post($url, ['title' => 'x', 'type' => 'file'])
            ->assertSessionHasErrors('attachment_file');

        $this->assertSame(0, CourseLesson::query()->count());
    }

    public function test_uploaded_video_and_attachment_are_stored_privately(): void
    {
        $section = $this->sectionWithCourse();

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.lessons.store', [$section->course_id, $section]), [
                'title' => 'درس مرفوع',
                'type' => 'video',
                'video_source' => 'upload',
                'video_file' => UploadedFile::fake()->create('lesson.mp4', 1024, 'video/mp4'),
                'attachments' => [
                    ['type' => 'file', 'title' => 'الشرائح', 'file' => UploadedFile::fake()->create('slides.pdf', 100, 'application/pdf')],
                ],
            ])
            ->assertSessionHasNoErrors();

        $lesson = CourseLesson::query()->firstOrFail();
        Storage::disk('local')->assertExists($lesson->video);
        $file = $lesson->attachments()->sole();
        $this->assertNotNull($file->path);
        Storage::disk('local')->assertExists($file->path);
        Storage::disk(config('storage.fallback_disk', 'public'))->assertMissing($file->path);
        Storage::disk(config('storage.fallback_disk', 'public'))->assertMissing($lesson->video);
        $this->assertNotNull($lesson->videoUrl());
    }

    public function test_a_lesson_can_have_many_named_links_and_files_in_order(): void
    {
        $section = $this->sectionWithCourse();

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.lessons.store', [$section->course_id, $section]), [
                'title' => 'درس بمرفقات',
                'type' => 'text',
                'content' => '<p>شرح</p>',
                'attachments' => [
                    ['type' => 'link', 'title' => 'ملفات المشروع', 'url' => 'https://drive.google.com/x'],
                    ['type' => 'file', 'title' => 'تمرين', 'file' => UploadedFile::fake()->create('exercise.zip', 300, 'application/zip')],
                    ['type' => 'link', 'title' => 'مرجع', 'url' => 'https://github.com/example/repo'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $attachments = CourseLesson::query()->firstOrFail()->attachments()->get();

        $this->assertSame(['ملفات المشروع', 'تمرين', 'مرجع'], $attachments->pluck('title')->all());
        $this->assertSame(['link', 'file', 'link'], $attachments->pluck('type')->all());
        $this->assertSame('exercise.zip', $attachments[1]->original_name);
        $this->assertSame('ri-drive-line', $attachments[0]->icon());
        $this->assertSame('ri-file-zip-line', $attachments[1]->icon());
    }

    public function test_editing_updates_reorders_and_removes_attachments_and_their_files(): void
    {
        $section = $this->sectionWithCourse();
        $admin = $this->adminUser();
        $store = route('admin.courses.lessons.store', [$section->course_id, $section]);

        $this->actingAs($admin)->post($store, [
            'title' => 'درس',
            'type' => 'text',
            'content' => '<p>x</p>',
            'attachments' => [
                ['type' => 'file', 'title' => 'قديم', 'file' => UploadedFile::fake()->create('old.pdf', 50, 'application/pdf')],
                ['type' => 'link', 'title' => 'رابط', 'url' => 'https://example.com/a'],
            ],
        ])->assertSessionHasNoErrors();

        $lesson = CourseLesson::query()->firstOrFail();
        [$file, $link] = $lesson->attachments()->get()->all();
        $oldPath = $file->path;

        // Keep the link (renamed, now first), drop the file.
        $this->actingAs($admin)->put(route('admin.courses.lessons.update', [$section->course_id, $lesson]), [
            'title' => 'درس',
            'type' => 'text',
            'content' => '<p>x</p>',
            'attachments_present' => '1',
            'attachments' => [
                ['id' => $link->id, 'type' => 'link', 'title' => 'رابط جديد', 'url' => 'https://example.com/b'],
                ['type' => 'link', 'title' => 'إضافي', 'url' => 'https://example.com/c'],
            ],
        ])->assertSessionHasNoErrors();

        $rows = $lesson->attachments()->get();
        $this->assertSame([$link->id], $rows->where('title', 'رابط جديد')->pluck('id')->values()->all());
        $this->assertSame(['رابط جديد', 'إضافي'], $rows->pluck('title')->all());
        Storage::disk('local')->assertMissing($oldPath);

        // An empty list removes everything.
        $this->actingAs($admin)->put(route('admin.courses.lessons.update', [$section->course_id, $lesson]), [
            'title' => 'درس',
            'type' => 'text',
            'content' => '<p>x</p>',
            'attachments_present' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $lesson->attachments()->count());
    }

    public function test_attachment_rows_are_validated(): void
    {
        $section = $this->sectionWithCourse();

        $this->actingAs($this->adminUser())
            ->post(route('admin.courses.lessons.store', [$section->course_id, $section]), [
                'title' => 'درس',
                'type' => 'text',
                'content' => '<p>x</p>',
                'attachments' => [
                    ['type' => 'link', 'title' => '', 'url' => 'ليس رابطاً'],
                    ['type' => 'file', 'title' => 'بلا ملف'],
                ],
            ])
            ->assertSessionHasErrors(['attachments.0.title', 'attachments.0.url', 'attachments.1.file']);

        $this->assertSame(0, CourseLesson::query()->count());
    }

    public function test_curriculum_page_sends_each_lessons_attachments(): void
    {
        $section = $this->sectionWithCourse();
        $lesson = CourseLesson::factory()->create(['course_section_id' => $section->id]);
        CourseLessonAttachment::factory()->create(['course_lesson_id' => $lesson->id, 'title' => 'المصادر']);

        $this->actingAs($this->adminUser())
            ->get(route('admin.courses.curriculum', $section->course_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sections.0.lessons.0.attachments.0.title', 'المصادر'));
    }

    public function test_replacing_upload_with_link_deletes_old_file(): void
    {
        Storage::disk('local')->put('lessons/old.mp4', 'video');
        $lesson = CourseLesson::factory()->create(['video_source' => VideoSource::Upload, 'video' => 'lessons/old.mp4']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.lessons.update', [$lesson->course_id, $lesson]), [
                'title' => $lesson->title,
                'type' => 'video',
                'video_source' => 'url',
                'video' => 'https://cdn.example.com/new.mp4',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://cdn.example.com/new.mp4', $lesson->fresh()->video);
        Storage::disk('local')->assertMissing('lessons/old.mp4');
    }

    public function test_update_keeps_existing_upload_when_no_new_file_is_sent(): void
    {
        Storage::disk('local')->put('lessons/keep.mp4', 'video');
        $lesson = CourseLesson::factory()->create(['video_source' => VideoSource::Upload, 'video' => 'lessons/keep.mp4']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.lessons.update', [$lesson->course_id, $lesson]), [
                'title' => 'عنوان معدّل',
                'type' => 'video',
                'video_source' => 'upload',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('lessons/keep.mp4', $lesson->fresh()->video);
        Storage::disk('local')->assertExists('lessons/keep.mp4');
    }

    public function test_switching_lesson_to_text_clears_video(): void
    {
        $lesson = CourseLesson::factory()->youtube()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.lessons.update', [$lesson->course_id, $lesson]), [
                'title' => $lesson->title,
                'type' => 'text',
                'content' => '<p>محتوى</p>',
            ])
            ->assertSessionHasNoErrors();

        $lesson->refresh();
        $this->assertSame(LessonType::Text, $lesson->type);
        $this->assertNull($lesson->video_source);
        $this->assertNull($lesson->video);
    }

    public function test_lesson_can_move_to_another_section_of_the_same_course_only(): void
    {
        $admin = $this->adminUser();
        $lesson = CourseLesson::factory()->create();
        $sameCourseSection = CourseSection::factory()->create(['course_id' => $lesson->course_id]);
        $otherCourseSection = $this->sectionWithCourse();
        $payload = ['title' => $lesson->title, 'type' => 'video', 'video_source' => 'url', 'video' => $lesson->video];

        $this->actingAs($admin)
            ->put(route('admin.courses.lessons.update', [$lesson->course_id, $lesson]), [...$payload, 'course_section_id' => $otherCourseSection->id])
            ->assertSessionHasErrors('course_section_id');

        $this->actingAs($admin)
            ->put(route('admin.courses.lessons.update', [$lesson->course_id, $lesson]), [...$payload, 'course_section_id' => $sameCourseSection->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($sameCourseSection->id, $lesson->fresh()->course_section_id);
    }

    public function test_lessons_reorder_within_section_only(): void
    {
        $admin = $this->adminUser();
        $section = $this->sectionWithCourse();
        $a = CourseLesson::factory()->create(['course_section_id' => $section->id, 'order' => 1]);
        $b = CourseLesson::factory()->create(['course_section_id' => $section->id, 'order' => 2]);
        $foreign = CourseLesson::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.courses.lessons.reorder', [$section->course_id, $section]), ['ids' => [$b->id, $a->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $b->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);

        $this->actingAs($admin)
            ->post(route('admin.courses.lessons.reorder', [$section->course_id, $section]), ['ids' => [$a->id, $foreign->id]])
            ->assertSessionHasErrors('ids');
    }

    public function test_deleting_lesson_and_section_refreshes_stats_and_files(): void
    {
        Storage::disk('local')->put('lessons/a.mp4', 'video');
        $section = $this->sectionWithCourse();
        $uploaded = CourseLesson::factory()->create([
            'course_section_id' => $section->id,
            'video_source' => VideoSource::Upload,
            'video' => 'lessons/a.mp4',
        ]);
        CourseLesson::factory()->create(['course_section_id' => $section->id]);
        $course = $section->course;
        $admin = $this->adminUser();

        $this->actingAs($admin)->delete(route('admin.courses.lessons.destroy', [$course, $uploaded]));
        Storage::disk('local')->assertMissing('lessons/a.mp4');
        $this->assertSame(1, $course->fresh()->lessons_count);

        $this->actingAs($admin)->delete(route('admin.courses.sections.destroy', [$course, $section]));
        $this->assertSame(0, $course->fresh()->lessons_count);
        $this->assertSame(0, CourseLesson::query()->where('course_id', $course->id)->count());
    }

    public function test_user_without_edit_permission_cannot_touch_curriculum(): void
    {
        $user = User::factory()->staff()->create();
        $user->givePermissionTo('admin.courses.list');
        $section = $this->sectionWithCourse();

        $this->actingAs($user)
            ->post(route('admin.courses.sections.store', $section->course_id), ['title' => 'x'])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.courses.lessons.store', [$section->course_id, $section]), ['title' => 'x', 'type' => 'text', 'content' => 'x'])
            ->assertForbidden();
    }

    public function test_section_description_accepts_rich_text(): void
    {
        $section = $this->sectionWithCourse();
        $html = '<p>في هذا القسم <strong>نتعلم</strong> الأساسيات.</p><ul><li>أولاً</li></ul>';

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.sections.update', [$section->course_id, $section]), [
                'title' => 'الأساسيات',
                'description' => $html,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($html, $section->fresh()->description);
    }

    public function test_renaming_a_section_keeps_its_description(): void
    {
        $section = CourseSection::factory()->create(['description' => '<p>وصف</p>']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.courses.sections.update', [$section->course_id, $section]), ['title' => 'عنوان جديد'])
            ->assertSessionHasNoErrors();

        $this->assertSame('<p>وصف</p>', $section->fresh()->description);
    }
}
