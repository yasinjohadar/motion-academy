<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use App\Services\Observability\SecurityLogger;
use App\Services\Users\CredentialsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Motion-Frame-4821!';

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('admin.users.edit', 'web');
        app(WhatsAppSettingsService::class)->updateSettings(['whatsapp_provider' => 'evolution']);
    }

    private function connectWhatsApp(): void
    {
        EvolutionInstance::query()->create(['instance_name' => 'test-instance', 'connection_status' => 'open']);
    }

    private function student(): User
    {
        return User::factory()->student()->create([
            'name' => 'سارة',
            'name_ar' => 'سارة',
            'email' => 'sara@example.com',
            'phone' => '+963 991 234 567',
            'phone_e164' => '963991234567',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'password' => self::PASSWORD,
            'send_whatsapp' => false,
            'message' => null,
            'save_template' => false,
            ...$overrides,
        ];
    }

    public function test_an_admin_sets_a_students_password(): void
    {
        $student = $this->student();
        $oldToken = $student->remember_token;

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.users.show', $student))
            ->put(route('admin.users.password', $student), $this->payload())
            ->assertRedirect(route('admin.users.show', $student))
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertTrue(Hash::check(self::PASSWORD, $student->password));
        $this->assertNotSame($oldToken, $student->remember_token);

        $event = Activity::query()->where('log_name', SecurityLogger::LOG_NAME)->where('event', 'password_set_by_admin')->sole();
        // The password itself is never logged.
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($event->properties));
    }

    public function test_the_details_go_out_on_whatsapp_and_the_log_hides_the_password(): void
    {
        Queue::fake();
        $this->connectWhatsApp();
        $student = $this->student();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.password', $student), $this->payload([
                'send_whatsapp' => true,
                'message' => "مرحباً {name}\nالبريد: {email}\nكلمة المرور: {password}\n{login_url}",
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // The student receives the real password…
        Queue::assertPushed(SendWhatsAppMessageJob::class, function (SendWhatsAppMessageJob $job): bool {
            return str_contains($job->messageData['text'], 'كلمة المرور: '.self::PASSWORD)
                && str_contains($job->messageData['text'], 'مرحباً سارة')
                && str_contains($job->messageData['text'], 'sara@example.com')
                && str_contains($job->messageData['text'], route('login'));
        });

        // …while the stored message (the admin's message log) shows it blanked.
        $stored = WhatsAppMessage::query()->sole();
        $this->assertStringNotContainsString(self::PASSWORD, $stored->body);
        $this->assertStringContainsString('••••••', $stored->body);

        // No in-app copy of a credential.
        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_without_whatsapp_the_password_still_changes_with_a_warning(): void
    {
        Queue::fake();
        $student = $this->student();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.password', $student), $this->payload([
                'send_whatsapp' => true,
                'message' => 'كلمة المرور: {password}',
            ]))
            ->assertSessionHas('warning');

        $this->assertTrue(Hash::check(self::PASSWORD, $student->fresh()->password));
        Queue::assertNothingPushed();
    }

    public function test_the_message_must_carry_the_password_and_the_password_be_long_enough(): void
    {
        $student = $this->student();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.password', $student), $this->payload(['send_whatsapp' => true, 'message' => 'مرحباً {name}']))
            ->assertSessionHasErrors('message');

        $this->actingAs($admin)
            ->put(route('admin.users.password', $student), $this->payload(['password' => 'short']))
            ->assertSessionHasErrors('password');

        $this->assertFalse(Hash::check(self::PASSWORD, $student->fresh()->password));
    }

    public function test_the_message_can_be_saved_as_the_default_template(): void
    {
        $student = $this->student();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.show', $student))
            ->assertInertia(fn ($page) => $page
                ->where('credentials.template', CredentialsMessage::PRESETS['formal']['text'])
                ->has('credentials.presets.friendly'));

        $this->connectWhatsApp();
        Queue::fake();
        $this->actingAs($admin)->put(route('admin.users.password', $student), $this->payload([
            'send_whatsapp' => true,
            'message' => 'قالبي: {password}',
            'save_template' => true,
        ]));

        $this->actingAs($admin)->get(route('admin.users.show', $student))
            ->assertInertia(fn ($page) => $page->where('credentials.template', 'قالبي: {password}'));
    }

    public function test_admin_accounts_and_users_without_permission_are_refused(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        $original = $otherAdmin->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.password', $otherAdmin), $this->payload())
            ->assertSessionHasErrors('password');
        $this->assertSame($original, $otherAdmin->fresh()->password);

        $this->actingAs($this->panelUserWithoutPermissions())
            ->put(route('admin.users.password', $this->student()), $this->payload())
            ->assertForbidden();
    }
}
