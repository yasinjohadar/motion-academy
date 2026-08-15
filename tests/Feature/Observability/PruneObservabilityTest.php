<?php

namespace Tests\Feature\Observability;

use App\Models\WhatsAppWebhookEvent;
use App\Services\Observability\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PruneObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function activityAged(string $logName, int $daysAgo): Activity
    {
        $activity = Activity::query()->create([
            'log_name' => $logName,
            'description' => 'test',
            'event' => 'updated',
        ]);

        Activity::query()->whereKey($activity->id)->update(['created_at' => now()->subDays($daysAgo)]);

        return $activity->refresh();
    }

    public function test_old_audit_rows_are_pruned(): void
    {
        config()->set('observability.retention.default', 180);

        $old = $this->activityAged('settings', 200);
        $recent = $this->activityAged('settings', 10);

        $this->artisan('observability:prune')->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $recent->id]);
    }

    public function test_security_rows_survive_the_shorter_audit_window(): void
    {
        config()->set('observability.retention.default', 180);
        config()->set('observability.retention.security', 365);

        // 200 days old: past the audit window, inside the security window.
        $security = $this->activityAged(SecurityLogger::LOG_NAME, 200);
        $audit = $this->activityAged('settings', 200);

        $this->artisan('observability:prune')->assertSuccessful();

        $this->assertDatabaseHas('activity_log', ['id' => $security->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $audit->id]);
    }

    public function test_very_old_security_rows_are_eventually_pruned(): void
    {
        config()->set('observability.retention.security', 365);

        $ancient = $this->activityAged(SecurityLogger::LOG_NAME, 400);

        $this->artisan('observability:prune')->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['id' => $ancient->id]);
    }

    public function test_only_processed_webhook_events_are_pruned(): void
    {
        config()->set('observability.retention.webhook_events', 30);

        $processed = WhatsAppWebhookEvent::query()->create([
            'event_id' => 'old-processed', 'payload' => ['event' => 'x'], 'processed_at' => now()->subDays(60),
        ]);
        $unprocessed = WhatsAppWebhookEvent::query()->create([
            'event_id' => 'old-unprocessed', 'payload' => ['event' => 'y'],
        ]);

        WhatsAppWebhookEvent::query()->whereIn('id', [$processed->id, $unprocessed->id])
            ->update(['created_at' => now()->subDays(60)]);

        $this->artisan('observability:prune')->assertSuccessful();

        // An unprocessed event is a lead worth keeping, however old.
        $this->assertDatabaseMissing('whatsapp_webhook_events', ['id' => $processed->id]);
        $this->assertDatabaseHas('whatsapp_webhook_events', ['id' => $unprocessed->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        config()->set('observability.retention.default', 30);

        $old = $this->activityAged('settings', 200);

        $this->artisan('observability:prune', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('activity_log', ['id' => $old->id]);
    }

    public function test_zero_retention_disables_pruning_for_that_bucket(): void
    {
        $old = $this->activityAged('settings', 5000);

        $this->artisan('observability:prune', [
            '--default-days' => 0,
            '--security-days' => 0,
            '--webhook-days' => 0,
        ])->assertSuccessful();

        $this->assertDatabaseHas('activity_log', ['id' => $old->id]);
    }

    public function test_retention_windows_can_be_overridden_with_options(): void
    {
        $old = $this->activityAged('settings', 10);

        $this->artisan('observability:prune', ['--default-days' => 5])->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['id' => $old->id]);
    }
}
