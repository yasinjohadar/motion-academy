<?php

namespace Tests\Feature\Observability;

use App\Models\AppNotification;
use App\Models\AppStorageConfig;
use App\Models\Concerns\AuditsChanges;
use App\Models\Media;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function smtpSetting(): SmtpSetting
    {
        return SmtpSetting::query()->create([
            'name' => 'Primary', 'host' => 'smtp.example.com', 'port' => 587,
            'username' => 'mailer', 'password' => 'secret', 'encryption' => 'tls',
            'from_address' => 'no-reply@example.com', 'from_name' => 'Test',
            'is_default' => true, 'is_active' => true,
        ]);
    }

    public function test_creating_an_audited_model_records_the_event_and_log_name(): void
    {
        $this->smtpSetting();

        $activity = Activity::query()->where('subject_type', SmtpSetting::class)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('created', $activity->event);
        $this->assertSame('settings', $activity->log_name);
    }

    public function test_updating_records_both_old_and_new_values(): void
    {
        $setting = $this->smtpSetting();

        $setting->update(['host' => 'smtp.changed.com']);

        $activity = Activity::query()->where('event', 'updated')->latest('id')->first();

        $this->assertSame('smtp.changed.com', $activity->properties['attributes']['host']);
        $this->assertSame('smtp.example.com', $activity->properties['old']['host']);
    }

    public function test_the_causer_is_the_acting_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->smtpSetting();

        $activity = Activity::query()->where('subject_type', SmtpSetting::class)->latest('id')->first();

        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame($user->getMorphClass(), $activity->causer_type);
    }

    public function test_the_causer_is_null_for_system_changes(): void
    {
        $this->smtpSetting();

        $activity = Activity::query()->where('subject_type', SmtpSetting::class)->latest('id')->first();

        $this->assertNull($activity->causer_id);
    }

    public function test_a_save_that_changes_nothing_records_no_activity(): void
    {
        $setting = $this->smtpSetting();
        $countAfterCreate = Activity::query()->count();

        $setting->update(['host' => 'smtp.example.com']);   // same value

        $this->assertSame($countAfterCreate, Activity::query()->count());
    }

    public function test_a_change_to_an_unaudited_field_records_no_activity(): void
    {
        $setting = $this->smtpSetting();
        $countAfterCreate = Activity::query()->count();

        // `password` is on the deny-list, so changing only it must not log.
        $setting->update(['password' => 'a-completely-new-password']);

        $this->assertSame($countAfterCreate, Activity::query()->count());
    }

    public function test_deleting_an_audited_model_is_recorded(): void
    {
        $this->smtpSetting()->delete();

        $this->assertTrue(
            Activity::query()->where('event', 'deleted')->where('subject_type', SmtpSetting::class)->exists()
        );
    }

    public function test_high_volume_models_are_deliberately_not_audited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $before = Activity::query()->count();

        // Media and notifications are excluded on purpose — auditing them would bury
        // the changes that matter under daily upload noise.
        Media::query()->create([
            'disk' => 'media', 'path' => 'x/y.jpg', 'filename' => 'y.jpg',
            'mime' => 'image/jpeg', 'extension' => 'jpg', 'size' => 1024,
        ]);

        $this->assertSame($before, Activity::query()->count());
        $this->assertArrayNotHasKey(Media::class, config('observability.audit.models'));
        $this->assertArrayNotHasKey(AppNotification::class, config('observability.audit.models'));
    }

    public function test_every_audited_model_actually_uses_the_trait(): void
    {
        foreach (array_keys(config('observability.audit.models')) as $model) {
            $this->assertContains(
                AuditsChanges::class,
                class_uses_recursive($model),
                "Model [{$model}] is configured for auditing but does not use the AuditsChanges trait."
            );
        }
    }

    public function test_migrations_do_not_write_activity_rows(): void
    {
        // Migrations that touch audited models (e.g. the storage conversion updating
        // an AppStorageConfig) run before activity_log exists on a fresh install;
        // they must run inside activity()->withoutLogs().
        $this->assertSame(
            0,
            DB::table('activity_log')->where('subject_type', AppStorageConfig::class)->count()
        );
    }
}
