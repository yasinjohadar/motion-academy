<?php

namespace Tests\Feature\Admin;

use App\Models\AiProvider;
use App\Models\User;
use App\Services\Ai\AiProviderConfigApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.ai-providers.list',
            'admin.ai-providers.create',
            'admin.ai-providers.edit',
            'admin.ai-providers.delete',
            'admin.ai-providers.test',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_view_ai_providers_index(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.ai-providers.index'))
            ->assertOk();
    }

    public function test_admin_can_create_ai_provider(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.ai-providers.store'), [
                'name' => 'OpenAI Main',
                'driver' => 'openai',
                'api_key' => 'sk-test-key-123',
                'base_url' => null,
                'default_model' => 'gpt-4o-mini',
                'is_default' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.ai-providers.index'));

        $this->assertDatabaseHas('ai_providers', [
            'name' => 'OpenAI Main',
            'driver' => 'openai',
            'is_default' => 1,
        ]);

        $provider = AiProvider::query()->first();
        $this->assertNotNull($provider);
        $this->assertSame('sk-test-key-123', $provider->api_key);
        $this->assertArrayNotHasKey('api_key', $provider->toAdminArray());
    }

    public function test_applier_writes_runtime_config_from_database(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Gemini',
            'driver' => 'gemini',
            'api_key' => 'gemini-secret',
            'default_model' => 'gemini-2.0-flash',
            'is_default' => true,
            'is_active' => true,
        ]);

        AiProviderConfigApplier::refresh();

        $this->assertSame($provider->configKey(), config('ai.default'));
        $this->assertSame('gemini-secret', config('ai.providers.'.$provider->configKey().'.key'));
        $this->assertSame('gemini-secret', config('ai.providers.gemini.key'));
    }

    public function test_admin_can_test_provider_connection_with_fake_agent(): void
    {
        AnonymousAgent::fake(['ok']);

        $provider = AiProvider::query()->create([
            'name' => 'OpenAI',
            'driver' => 'openai',
            'api_key' => 'sk-test',
            'default_model' => 'gpt-4o-mini',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.ai-providers.test', $provider))
            ->assertRedirect();

        $this->assertTrue(session()->has('success'));
    }

    public function test_cannot_delete_default_provider(): void
    {
        $provider = AiProvider::query()->create([
            'name' => 'Default',
            'driver' => 'openai',
            'api_key' => 'sk-test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->delete(route('admin.ai-providers.destroy', $provider))
            ->assertRedirect();

        $this->assertDatabaseHas('ai_providers', ['id' => $provider->id, 'deleted_at' => null]);
    }
}
