<?php

namespace Tests\Feature\Policies;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Per-row `can` flags: the piece that stops the UI offering buttons that 403.
 */
class PolicyFlagsTest extends TestCase
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

    protected function postBy(User $author, string $slug): BlogPost
    {
        return BlogPost::query()->create([
            'title' => 'مقال', 'slug' => $slug, 'content' => 'نص',
            'status' => 'draft', 'author_id' => $author->id,
        ]);
    }

    public function test_flags_are_true_for_a_record_the_user_owns(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit', 'admin.blog-posts.delete']);
        Auth::login($user);

        $flags = $this->postBy($user, 'mine')->toAdminArray(false)['can'];

        $this->assertTrue($flags['update']);
        $this->assertTrue($flags['delete']);
    }

    public function test_flags_are_false_for_a_record_owned_by_someone_else(): void
    {
        $user = $this->userWith(['admin.blog-posts.edit', 'admin.blog-posts.delete']);
        Auth::login($user);

        $flags = $this->postBy(User::factory()->create(), 'theirs')->toAdminArray(false)['can'];

        $this->assertFalse($flags['update']);
        $this->assertFalse($flags['delete']);
    }

    public function test_flags_reflect_the_any_grant(): void
    {
        $user = $this->userWith([
            'admin.blog-posts.edit', 'admin.blog-posts.edit-any', 'admin.blog-posts.delete',
        ]);
        Auth::login($user);

        $flags = $this->postBy(User::factory()->create(), 'delegated')->toAdminArray(false)['can'];

        // edit-any granted, delete-any not — the row shows edit but hides delete.
        $this->assertTrue($flags['update']);
        $this->assertFalse($flags['delete']);
    }

    public function test_flags_are_false_for_a_guest(): void
    {
        $flags = $this->postBy(User::factory()->create(), 'guest')->toAdminArray(false)['can'];

        $this->assertFalse($flags['update']);
        $this->assertFalse($flags['delete']);
    }

    public function test_the_index_payload_carries_flags_for_every_row(): void
    {
        $user = $this->userWith([
            'admin.blog-posts.list', 'admin.blog-posts.edit', 'admin.blog-posts.delete',
        ]);

        $this->postBy($user, 'own-post');
        $this->postBy(User::factory()->create(), 'other-post');

        $this->actingAs($user)
            ->get(route('admin.blog-posts.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $rows = collect($page->toArray()['props']['posts']['data']);

                $this->assertCount(2, $rows);
                $rows->each(fn (array $row) => $this->assertArrayHasKey('can', $row));

                $own = $rows->firstWhere('slug', 'own-post');
                $other = $rows->firstWhere('slug', 'other-post');

                $this->assertTrue($own['can']['update']);
                $this->assertFalse($other['can']['update']);
            });
    }

    /**
     * Serialise `$count` rows the way BlogPostController::index does — with the same
     * eager loads — and report how many queries it took.
     */
    protected function queriesToSerialise(User $user, int $count): int
    {
        Auth::login($user);
        $user->can('admin.blog-posts.edit');   // warm spatie's permission cache

        $queries = 0;
        $listener = function () use (&$queries): void {
            $queries++;
        };

        DB::listen($listener);

        BlogPost::query()
            ->with(['author:id,name', 'category:id,name', 'tags:id,name'])
            ->limit($count)
            ->get()
            ->each(fn (BlogPost $post) => $post->toAdminArray(false));

        return $queries;
    }

    public function test_policy_flags_do_not_add_a_query_per_row(): void
    {
        $user = $this->userWith([
            'admin.blog-posts.list', 'admin.blog-posts.edit', 'admin.blog-posts.delete',
        ]);

        foreach (range(1, 20) as $i) {
            $this->postBy($user, "perf-{$i}");
        }

        $forFive = $this->queriesToSerialise($user, 5);
        $forTwenty = $this->queriesToSerialise($user, 20);

        // Scaling is the honest question. The counts are not identical — the first
        // pass also warms spatie's role cache — but they must not GROW with the row
        // count. If Gate queried per row, 20 rows would cost ~15 more than 5.
        $this->assertLessThanOrEqual(
            $forFive,
            $forTwenty,
            "Serialising 5 rows took {$forFive} queries but 20 rows took {$forTwenty} — "
                .'policy flags are hitting the database per row.'
        );

        // And the absolute cost stays at the handful of eager loads the controller asks for.
        $this->assertLessThanOrEqual(6, $forTwenty);
    }
}
