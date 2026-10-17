<?php

namespace Tests\Feature\Admin;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewsletterSubscriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['list', 'create', 'edit', 'delete', 'export'] as $action) {
            Permission::findOrCreate("admin.newsletter-subscribers.{$action}", 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    public function test_index_lists_subscribers_with_stats_and_filters(): void
    {
        NewsletterSubscriber::factory()->count(3)->create(['source' => 'footer']);
        NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com', 'source' => 'home']);

        $this->actingAs($this->admin())
            ->get(route('admin.newsletter.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Newsletter/Index')
                ->where('stats.total', 4)
                ->where('stats.subscribed', 3)
                ->where('stats.unsubscribed', 1)
                ->has('subscribers.data', 4));

        $this->actingAs($this->admin())
            ->get(route('admin.newsletter.index', ['status' => 'unsubscribed']))
            ->assertInertia(fn ($page) => $page
                ->has('subscribers.data', 1)
                ->where('subscribers.data.0.email', 'gone@example.com'));

        $this->actingAs($this->admin())
            ->get(route('admin.newsletter.index', ['source' => 'footer', 'query' => 'gone']))
            ->assertInertia(fn ($page) => $page->has('subscribers.data', 0));
    }

    public function test_admin_can_add_toggle_and_delete(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.newsletter.store'), ['email' => 'New@Example.com', 'name' => 'قارئ'])
            ->assertSessionHasNoErrors();

        $subscriber = NewsletterSubscriber::query()->sole();
        $this->assertSame('new@example.com', $subscriber->email);
        $this->assertSame('admin', $subscriber->source);

        $this->actingAs($admin)
            ->post(route('admin.newsletter.store'), ['email' => 'new@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('admin.newsletter.toggle', $subscriber));
        $this->assertFalse($subscriber->fresh()->isSubscribed());
        $this->actingAs($admin)->post(route('admin.newsletter.toggle', $subscriber));
        $this->assertTrue($subscriber->fresh()->isSubscribed());

        $this->actingAs($admin)->delete(route('admin.newsletter.destroy', $subscriber));
        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_export_streams_a_csv_of_the_filtered_list(): void
    {
        NewsletterSubscriber::factory()->create(['email' => 'in@example.com']);
        NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'out@example.com']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.newsletter.export', ['status' => 'subscribed']))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('email,name,status', $csv);
        $this->assertStringContainsString('in@example.com', $csv);
        $this->assertStringNotContainsString('out@example.com', $csv);
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $user = $this->panelUserWithoutPermissions();

        $this->actingAs($user)->get(route('admin.newsletter.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.newsletter.export'))->assertForbidden();
    }
}
