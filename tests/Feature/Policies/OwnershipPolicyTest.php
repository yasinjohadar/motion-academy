<?php

namespace Tests\Feature\Policies;

use App\Models\BlogPost;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ownership rules. Every test uses a NON-admin user — Gate::before hands admins every
 * ability, so an admin-based test would pass even with the policies deleted.
 */
class OwnershipPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function userWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function postBy(?User $author): BlogPost
    {
        return BlogPost::query()->create([
            'title' => 'مقال', 'slug' => 'post-'.uniqid(), 'content' => 'محتوى',
            'status' => 'draft', 'author_id' => $author?->id,
        ]);
    }

    // ------------------------------------------------------------------ owner

    public function test_the_owner_may_update_their_own_record(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit']);

        $this->assertTrue(Gate::forUser($user)->allows('update', $this->postBy($user)));
    }

    public function test_the_owner_may_delete_their_own_record(): void
    {
        $user = $this->userWith(['admin.blog-posts.delete']);

        $this->assertTrue(Gate::forUser($user)->allows('delete', $this->postBy($user)));
    }

    // ------------------------------------------------------------------ non-owner

    public function test_a_non_owner_with_only_the_base_permission_is_denied(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit', 'admin.blog-posts.delete']);
        $post = $this->postBy(User::factory()->create());

        // This is the hole the whole layer closes: today any edit permission edits
        // every post regardless of who wrote it.
        $this->assertFalse(Gate::forUser($user)->allows('update', $post));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $post));
    }

    public function test_the_denial_carries_a_readable_arabic_reason(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit']);
        $post = $this->postBy(User::factory()->create());

        $response = Gate::forUser($user)->inspect('update', $post);

        $this->assertTrue($response->denied());
        $this->assertSame('لا تملك صلاحية تعديل عنصر أنشأه مستخدم آخر.', $response->message());
    }

    // ------------------------------------------------------------------ -any grants

    public function test_edit_any_allows_editing_another_users_record(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit', 'admin.blog-posts.edit-any']);
        $post = $this->postBy(User::factory()->create());

        $this->assertTrue(Gate::forUser($user)->allows('update', $post));
    }

    public function test_edit_any_does_not_imply_delete_any(): void
    {
        $user = $this->userWith([
            'admin.blog-posts.edit', 'admin.blog-posts.edit-any',
            'admin.blog-posts.delete',
        ]);
        $post = $this->postBy(User::factory()->create());

        // The two grants are deliberately independent.
        $this->assertTrue(Gate::forUser($user)->allows('update', $post));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $post));
    }

    public function test_delete_any_allows_deleting_another_users_record(): void
    {
        $user = $this->userWith(['admin.blog-posts.delete', 'admin.blog-posts.delete-any']);
        $post = $this->postBy(User::factory()->create());

        $this->assertTrue(Gate::forUser($user)->allows('delete', $post));
    }

    public function test_the_any_grant_alone_is_not_enough_without_the_base_permission(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit-any']);

        $this->assertFalse(Gate::forUser($user)->allows('update', $this->postBy($user)));
    }

    // ------------------------------------------------------------------ edge cases

    public function test_a_record_with_no_owner_requires_the_any_grant(): void
    {
        $withoutAny = $this->userWith(['admin.blog-posts.edit']);
        $withAny = $this->userWith(['admin.blog-posts.edit', 'admin.blog-posts.edit-any']);
        $orphan = $this->postBy(null);

        // A null creator belongs to nobody — treating it as "mine" would let any
        // editor claim legacy rows.
        $this->assertFalse(Gate::forUser($withoutAny)->allows('update', $orphan));
        $this->assertTrue(Gate::forUser($withAny)->allows('update', $orphan));
    }

    public function test_ownership_applies_across_every_ownable_resource(): void
    {
        $owner = $this->userWith(['admin.site-pages.edit']);
        $other = $this->userWith(['admin.site-pages.edit']);

        $page = SitePage::query()->create([
            'title' => 'صفحة', 'slug' => 'page-'.uniqid(), 'body' => 'نص',
            'is_published' => false, 'created_by' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $page));
        $this->assertFalse(Gate::forUser($other)->allows('update', $page));
    }

    public function test_viewing_is_not_restricted_by_ownership(): void
    {
        $user = $this->userWith(['admin.blog-posts.list']);
        $post = $this->postBy(User::factory()->create());

        // Lists stay complete; only the action buttons narrow.
        $this->assertTrue(Gate::forUser($user)->allows('view', $post));
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', BlogPost::class));
    }
}
