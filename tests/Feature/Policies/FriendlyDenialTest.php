<?php

namespace Tests\Feature\Policies;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Two kinds of refusal, two different answers:
 *
 *   permission middleware → "you may not reach this screen"      → 403
 *   policy denial         → "you may not do this to this record" → inline message
 *
 * Both halves are asserted here so the distinction cannot silently collapse.
 */
class FriendlyDenialTest extends TestCase
{
    use RefreshDatabase;

    protected function userWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->staff()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function postByOther(): BlogPost
    {
        return BlogPost::query()->create([
            'title' => 'مقال', 'slug' => 'not-mine', 'content' => 'نص',
            'status' => 'draft', 'author_id' => User::factory()->create()->id,
        ]);
    }

    public function test_a_policy_denial_redirects_back_with_the_arabic_reason(): void
    {
        $user = $this->userWith(['admin.blog-posts.list', 'admin.blog-posts.edit']);

        $this->actingAs($user)
            ->from(route('admin.blog-posts.index'))
            ->get(route('admin.blog-posts.edit', $this->postByOther()->id))
            ->assertRedirect(route('admin.blog-posts.index'))
            ->assertSessionHas('error', 'لا تملك صلاحية تعديل عنصر أنشأه مستخدم آخر.');
    }

    public function test_a_policy_denial_on_a_write_action_also_redirects(): void
    {
        $user = $this->userWith(['admin.blog-posts.list', 'admin.blog-posts.delete']);

        $this->actingAs($user)
            ->from(route('admin.blog-posts.index'))
            ->delete(route('admin.blog-posts.destroy', $this->postByOther()->id))
            ->assertRedirect(route('admin.blog-posts.index'))
            ->assertSessionHas('error', 'لا تملك صلاحية حذف عنصر أنشأه مستخدم آخر.');
    }

    public function test_the_record_is_untouched_after_a_denied_delete(): void
    {
        $user = $this->userWith(['admin.blog-posts.list', 'admin.blog-posts.delete']);
        $post = $this->postByOther();

        $this->actingAs($user)
            ->from(route('admin.blog-posts.index'))
            ->delete(route('admin.blog-posts.destroy', $post->id));

        $this->assertNotNull(BlogPost::query()->find($post->id));
    }

    public function test_missing_screen_permission_still_returns_403(): void
    {
        // Spatie's middleware denial keeps the hard 403 — this is the boundary the
        // friendly handler must not cross.
        $user = $this->userWith(['admin.blog-posts.list']);

        $this->actingAs($user)
            ->get(route('admin.smtp-settings.index'))
            ->assertForbidden();
    }

    public function test_a_json_request_still_receives_a_status_not_a_redirect(): void
    {
        $user = $this->userWith(['admin.blog-posts.list', 'admin.blog-posts.edit']);

        $this->actingAs($user)
            ->getJson(route('admin.blog-posts.edit', $this->postByOther()->id))
            ->assertForbidden();
    }

    public function test_the_owner_is_not_redirected(): void
    {
        $user = $this->userWith(['admin.blog-posts.list', 'admin.blog-posts.edit']);

        $own = BlogPost::query()->create([
            'title' => 'مقالي', 'slug' => 'mine', 'content' => 'نص',
            'status' => 'draft', 'author_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.blog-posts.edit', $own->id))
            ->assertOk();
    }
}
