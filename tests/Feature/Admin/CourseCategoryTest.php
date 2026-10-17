<?php

namespace Tests\Feature\Admin;

use App\Models\CourseCategory;
use App\Models\StorageDiskMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.course-categories.list',
            'admin.course-categories.create',
            'admin.course-categories.edit',
            'admin.course-categories.delete',
            'admin.course-categories.restore',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());

        // The migration maps the "courses" disk to the real local storage; drop it so
        // uploads fall through to the faked fallback disk.
        StorageDiskMapping::query()->where('disk_name', CourseCategory::STORAGE_DISK)->delete();
        Storage::fake(config('storage.fallback_disk', 'public'));
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_index_shows_two_level_tree_without_filters(): void
    {
        $root = CourseCategory::factory()->create(['name' => 'برمجة', 'order' => 1]);
        CourseCategory::factory()->childOf($root)->create(['name' => 'تطوير ويب']);
        CourseCategory::factory()->create(['name' => 'تصميم', 'order' => 2]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.course-categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Categories/Index')
                ->has('tree', 2)
                ->where('tree.0.name', 'برمجة')
                ->has('tree.0.children', 1)
                ->where('tree.0.children.0.name', 'تطوير ويب')
                ->where('categories', null)
                ->where('stats.total', 3)
                ->where('stats.roots', 2));
    }

    public function test_index_filters_switch_to_flat_paginated_list(): void
    {
        $root = CourseCategory::factory()->create(['name' => 'برمجة']);
        CourseCategory::factory()->childOf($root)->inactive()->create(['name' => 'تطوير ويب']);
        CourseCategory::factory()->featured()->create(['name' => 'تصميم']);

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.course-categories.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree', null)
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'تطوير ويب'));

        $this->actingAs($admin)
            ->get(route('admin.course-categories.index', ['featured' => '1']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'تصميم'));

        $this->actingAs($admin)
            ->get(route('admin.course-categories.index', ['parent' => (string) $root->id]))
            ->assertInertia(fn (Assert $page) => $page->has('categories.data', 1));

        $this->actingAs($admin)
            ->get(route('admin.course-categories.index', ['query' => 'تصم']))
            ->assertInertia(fn (Assert $page) => $page->has('categories.data', 1));
    }

    public function test_admin_can_create_category_with_arabic_slug_and_order_appended(): void
    {
        CourseCategory::factory()->create(['order' => 4]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), [
                'name' => 'تطوير الويب',
                'icon' => 'ri-code-s-slash-line',
                'color' => '#2563eb',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.course-categories.index'));

        $category = CourseCategory::query()->where('name', 'تطوير الويب')->firstOrFail();
        $this->assertSame('تطوير-الويب', $category->slug);
        $this->assertSame(5, $category->order);
        $this->assertTrue($category->isRoot());
    }

    public function test_category_can_be_hidden_from_search_engines(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), ['name' => 'تصميم', 'is_indexable' => '0'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), ['name' => 'برمجة'])
            ->assertSessionHasNoErrors();

        $this->assertFalse(CourseCategory::query()->where('name', 'تصميم')->sole()->is_indexable);
        $this->assertTrue(CourseCategory::query()->where('name', 'برمجة')->sole()->is_indexable);
    }

    public function test_slug_stays_unique_even_against_trashed_categories(): void
    {
        $trashed = CourseCategory::factory()->create(['name' => 'برمجة', 'slug' => 'برمجة']);
        $trashed->delete();

        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), ['name' => 'برمجة'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('course_categories', ['slug' => 'برمجة-2', 'deleted_at' => null]);
    }

    public function test_admin_can_upload_category_image(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), [
                'name' => 'تصميم',
                'image' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $category = CourseCategory::query()->firstOrFail();
        $this->assertNotNull($category->image);
        $this->assertTrue($category->hasUploadedImage());
        Storage::disk(config('storage.fallback_disk', 'public'))->assertExists($category->image);
    }

    public function test_admin_can_pick_image_from_library_and_remove_it(): void
    {
        $admin = $this->adminUser();
        $category = CourseCategory::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.course-categories.update', $category), [
                'name' => $category->name,
                'image_url' => 'https://cdn.example.com/cover.webp',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://cdn.example.com/cover.webp', $category->fresh()->image);
        $this->assertSame('https://cdn.example.com/cover.webp', $category->fresh()->imageUrl());

        $this->actingAs($admin)
            ->put(route('admin.course-categories.update', $category), [
                'name' => $category->name,
                'remove_image' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->image);
    }

    public function test_update_without_image_fields_keeps_existing_image(): void
    {
        $category = CourseCategory::factory()->create(['image' => 'https://cdn.example.com/a.webp']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.course-categories.update', $category), [
                'name' => 'اسم جديد',
                'slug' => 'new-slug',
            ])
            ->assertRedirect(route('admin.course-categories.index'));

        $category->refresh();
        $this->assertSame('اسم جديد', $category->name);
        $this->assertSame('new-slug', $category->slug);
        $this->assertSame('https://cdn.example.com/a.webp', $category->image);
    }

    public function test_parent_must_be_a_root_category(): void
    {
        $root = CourseCategory::factory()->create();
        $child = CourseCategory::factory()->childOf($root)->create();

        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), [
                'name' => 'مستوى ثالث',
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('course_categories', ['name' => 'مستوى ثالث']);
    }

    public function test_category_with_children_cannot_become_a_child(): void
    {
        $root = CourseCategory::factory()->create();
        CourseCategory::factory()->childOf($root)->create();
        $otherRoot = CourseCategory::factory()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.course-categories.update', $root), [
                'name' => $root->name,
                'parent_id' => $otherRoot->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = CourseCategory::factory()->create();

        $this->actingAs($this->adminUser())
            ->put(route('admin.course-categories.update', $category), [
                'name' => $category->name,
                'parent_id' => $category->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_invalid_color_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), ['name' => 'x', 'color' => 'red'])
            ->assertSessionHasErrors('color');
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $root = CourseCategory::factory()->create();
        CourseCategory::factory()->childOf($root)->create();

        $this->actingAs($this->adminUser())
            ->delete(route('admin.course-categories.destroy', $root))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($root);
    }

    public function test_delete_moves_to_trash_and_restore_brings_it_back(): void
    {
        $admin = $this->adminUser();
        $category = CourseCategory::factory()->create(['name' => 'محذوف']);

        $this->actingAs($admin)
            ->delete(route('admin.course-categories.destroy', $category))
            ->assertSessionHas('success');
        $this->assertSoftDeleted($category);

        $this->actingAs($admin)
            ->get(route('admin.course-categories.trash'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Categories/Trash')
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'محذوف'));

        $this->actingAs($admin)
            ->post(route('admin.course-categories.restore', $category))
            ->assertSessionHas('success');
        $this->assertNotSoftDeleted($category);
    }

    public function test_child_cannot_be_restored_while_parent_is_trashed(): void
    {
        $admin = $this->adminUser();
        $root = CourseCategory::factory()->create();
        $child = CourseCategory::factory()->childOf($root)->create();
        $child->delete();
        $root->delete();

        $this->actingAs($admin)
            ->post(route('admin.course-categories.restore', $child))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($child);
    }

    public function test_force_delete_removes_record_and_uploaded_image(): void
    {
        $disk = Storage::disk(config('storage.fallback_disk', 'public'));
        $disk->put('categories/cover.jpg', 'image');
        $category = CourseCategory::factory()->create(['image' => 'categories/cover.jpg']);
        $orphan = CourseCategory::factory()->childOf($category)->create();
        $orphan->delete();
        $category->delete();

        $this->actingAs($this->adminUser())
            ->delete(route('admin.course-categories.force-delete', $category))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('course_categories', ['id' => $category->id]);
        $disk->assertMissing('categories/cover.jpg');
        $this->assertNull(CourseCategory::withTrashed()->find($orphan->id)->parent_id);
    }

    public function test_force_delete_requires_category_to_be_trashed_first(): void
    {
        $category = CourseCategory::factory()->create();

        $this->actingAs($this->adminUser())
            ->delete(route('admin.course-categories.force-delete', $category))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('course_categories', ['id' => $category->id]);
    }

    public function test_siblings_can_be_reordered(): void
    {
        $a = CourseCategory::factory()->create(['order' => 1]);
        $b = CourseCategory::factory()->create(['order' => 2]);
        $c = CourseCategory::factory()->create(['order' => 3]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $c->fresh()->order);
        $this->assertSame(2, $a->fresh()->order);
        $this->assertSame(3, $b->fresh()->order);
    }

    public function test_reorder_rejects_categories_from_different_levels(): void
    {
        $root = CourseCategory::factory()->create(['order' => 1]);
        $child = CourseCategory::factory()->childOf($root)->create(['order' => 1]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.reorder'), ['ids' => [$child->id, $root->id]])
            ->assertSessionHasErrors('ids');

        $this->assertSame(1, $root->fresh()->order);
    }

    public function test_toggle_active_and_featured(): void
    {
        $admin = $this->adminUser();
        $category = CourseCategory::factory()->create(['is_active' => true, 'is_featured' => false]);

        $this->actingAs($admin)->post(route('admin.course-categories.toggle-active', $category));
        $this->actingAs($admin)->post(route('admin.course-categories.toggle-featured', $category));

        $category->refresh();
        $this->assertFalse($category->is_active);
        $this->assertTrue($category->is_featured);
    }

    public function test_create_and_edit_pages_render(): void
    {
        $admin = $this->adminUser();
        $root = CourseCategory::factory()->create();
        $child = CourseCategory::factory()->childOf($root)->create();

        $this->actingAs($admin)
            ->get(route('admin.course-categories.create', ['parent' => $root->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Categories/Create')
                ->where('defaultParentId', $root->id)
                ->has('roots', 1));

        $this->actingAs($admin)
            ->get(route('admin.course-categories.edit', $root))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses/Categories/Edit')
                ->where('category.has_children', true)
                ->has('roots', 0));

        $this->actingAs($admin)
            ->get(route('admin.course-categories.edit', $child))
            ->assertInertia(fn (Assert $page) => $page->where('category.has_children', false));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->panelUserWithoutPermissions();
        $category = CourseCategory::factory()->create();

        $this->actingAs($user)->get(route('admin.course-categories.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.course-categories.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.course-categories.destroy', $category))->assertForbidden();
        $this->actingAs($user)->get(route('admin.course-categories.trash'))->assertForbidden();
    }

    public function test_list_only_user_cannot_reorder_or_restore(): void
    {
        $user = User::factory()->staff()->create();
        $user->givePermissionTo('admin.course-categories.list');
        $category = CourseCategory::factory()->create();

        $this->actingAs($user)->get(route('admin.course-categories.index'))->assertOk();
        $this->actingAs($user)
            ->post(route('admin.course-categories.reorder'), ['ids' => [$category->id]])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.course-categories.restore', $category))
            ->assertForbidden();
    }

    public function test_category_description_accepts_rich_text_and_lists_a_plain_excerpt(): void
    {
        $html = '<p>دورات <strong>البرمجة</strong> للمبتدئين</p>';

        $this->actingAs($this->adminUser())
            ->post(route('admin.course-categories.store'), ['name' => 'برمجة', 'description' => $html])
            ->assertSessionHasNoErrors();

        $category = CourseCategory::query()->firstOrFail();
        $this->assertSame($html, $category->description);
        $this->assertSame('دورات البرمجة للمبتدئين', $category->toAdminArray()['description_excerpt']);
    }
}
