<?php

namespace Tests\Feature\Observability;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatabaseScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_keys(config('permissions.panels.admin.groups.observability.permissions')) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    // ---------------------------------------------------------------- access

    public function test_guests_are_redirected(): void
    {
        $this->get(route('admin.observability.database'))->assertRedirect(route('login'));
    }

    public function test_the_screen_requires_its_own_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.observability.database'))
            ->assertForbidden();
    }

    public function test_other_observability_permissions_do_not_grant_access(): void
    {
        // The screen exposes the host, username and full schema — deliberately not
        // covered by the audit-log permission.
        $user = $this->userWith(['admin.observability.activity', 'admin.observability.jobs']);

        $this->actingAs($user)->get(route('admin.observability.database'))->assertForbidden();
        $this->actingAs($user)->getJson(route('admin.observability.database.table', 'users'))->assertForbidden();
    }

    public function test_the_database_permission_grants_access(): void
    {
        $this->actingAs($this->userWith(['admin.observability.database']))
            ->get(route('admin.observability.database'))
            ->assertOk();
    }

    public function test_the_hub_is_reachable_with_only_the_database_permission(): void
    {
        $this->actingAs($this->userWith(['admin.observability.database']))
            ->get(route('admin.observability.index'))
            ->assertOk();
    }

    // ---------------------------------------------------------------- payload

    public function test_the_page_carries_connection_tables_totals_and_notes(): void
    {
        $this->actingAs($this->userWith(['admin.observability.database']))
            ->get(route('admin.observability.database'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Observability/Database')
                ->where('connection.driver', 'sqlite')
                ->has('connection.settings')
                ->has('tables')
                ->has('totals.tables')
                ->has('notes')
            );
    }

    public function test_the_rendered_page_never_contains_the_database_password(): void
    {
        $secret = 'RenderedPagePassword2026';

        config()->set('database.connections.sqlite.password', $secret);
        config()->set('database.connections.sqlite.url', 'mysql://root:'.$secret.'@host/db');

        $response = $this->actingAs($this->userWith(['admin.observability.database']))
            ->get(route('admin.observability.database'));

        // The whole rendered response, not just the props we happen to inspect.
        $response->assertOk();
        $this->assertStringNotContainsString($secret, $response->getContent());
    }

    public function test_the_table_counts_reported_match_the_database(): void
    {
        User::factory()->count(6)->create();

        $this->actingAs($this->userWith(['admin.observability.database']))
            ->get(route('admin.observability.database'))
            ->assertInertia(function ($page) {
                $tables = collect($page->toArray()['props']['tables']);
                $users = $tables->firstWhere('name', 'users');

                // 6 created + the one granted the permission above.
                $this->assertSame(7, $users['rows']);
                $this->assertSame($tables->count(), $page->toArray()['props']['totals']['tables']);
            });
    }

    // ---------------------------------------------------------------- table detail

    public function test_table_detail_returns_json(): void
    {
        $this->actingAs($this->userWith(['admin.observability.database']))
            ->getJson(route('admin.observability.database.table', 'users'))
            ->assertOk()
            ->assertJsonStructure(['name', 'rows', 'columns', 'indexes', 'foreign_keys']);
    }

    public function test_an_unknown_table_returns_a_json_404(): void
    {
        $this->actingAs($this->userWith(['admin.observability.database']))
            ->getJson(route('admin.observability.database.table', 'not_a_real_table'))
            ->assertNotFound()
            ->assertJson(['message' => 'الجدول غير موجود.']);
    }

    public function test_the_route_pattern_rejects_a_table_name_with_sql_characters(): void
    {
        // The {table} segment is constrained to [A-Za-z0-9_]+ so a crafted name never
        // even reaches the controller.
        $this->actingAs($this->userWith(['admin.observability.database']))
            ->getJson('/admin/observability/database/users;drop')
            ->assertNotFound();

        $this->assertDatabaseCount('users', 1);
    }
}
