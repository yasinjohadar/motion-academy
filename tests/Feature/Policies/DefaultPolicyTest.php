<?php

namespace Tests\Feature\Policies;

use App\Models\AiProvider;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Guards the trap this whole layer is designed around.
 *
 * Gate::allows() returns FALSE for a model with no registered policy. Once list rows
 * carry `can` flags, forgetting a policy silently strips the edit/delete buttons from
 * a screen. These tests fail loudly instead.
 *
 * Everything here runs as a NON-admin: Gate::before grants admins every ability, so
 * an admin-based test would pass no matter how broken the policies are.
 */
class DefaultPolicyTest extends TestCase
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

    protected function smtpSetting(): SmtpSetting
    {
        return SmtpSetting::query()->create([
            'name' => 'Primary', 'host' => 'smtp.example.com', 'port' => 587,
            'username' => 'u', 'password' => 'p', 'encryption' => 'tls',
            'from_address' => 'a@b.c', 'from_name' => 'T', 'is_default' => true, 'is_active' => true,
        ]);
    }

    public function test_a_permission_holder_can_update_a_resource_that_has_no_owner(): void
    {
        $user = $this->userWith(['admin.smtp-settings.edit']);

        // The regression this file exists for: without SmtpSettingPolicy this is false
        // and every edit button on the SMTP screen quietly disappears.
        $this->assertTrue(Gate::forUser($user)->allows('update', $this->smtpSetting()));
    }

    public function test_a_permission_holder_can_delete_a_resource_that_has_no_owner(): void
    {
        $user = $this->userWith(['admin.smtp-settings.delete']);

        $this->assertTrue(Gate::forUser($user)->allows('delete', $this->smtpSetting()));
    }

    public function test_a_user_without_the_permission_is_denied(): void
    {
        $user = $this->userWith(['admin.smtp-settings.list']);

        $this->assertFalse(Gate::forUser($user)->allows('update', $this->smtpSetting()));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $this->smtpSetting()));
    }

    public function test_view_any_follows_the_list_permission(): void
    {
        $withList = $this->userWith(['admin.smtp-settings.list']);
        $without = User::factory()->create();

        $this->assertTrue(Gate::forUser($withList)->allows('viewAny', SmtpSetting::class));
        $this->assertFalse(Gate::forUser($without)->allows('viewAny', SmtpSetting::class));
    }

    public function test_create_follows_the_create_permission(): void
    {
        $user = $this->userWith(['admin.smtp-settings.create']);

        $this->assertTrue(Gate::forUser($user)->allows('create', SmtpSetting::class));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('create', SmtpSetting::class));
    }

    public function test_the_default_policy_applies_to_every_non_ownable_resource(): void
    {
        $user = $this->userWith(['admin.ai-providers.edit']);

        $provider = AiProvider::query()->create([
            'name' => 'OpenAI', 'driver' => 'openai', 'api_key' => 'k',
            'base_url' => 'https://api.openai.com', 'default_model' => 'gpt-4',
            'is_default' => true, 'is_active' => true,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('update', $provider));
    }

    public function test_permission_names_are_built_from_the_central_registry(): void
    {
        $crud = Permissions::crud('admin', 'blog-posts');

        $this->assertSame('admin.blog-posts.edit', $crud['edit']);
        $this->assertSame('admin.blog-posts.edit-any', $crud['editAny']);
        $this->assertSame('admin.blog-posts.delete-any', $crud['deleteAny']);
    }
}
