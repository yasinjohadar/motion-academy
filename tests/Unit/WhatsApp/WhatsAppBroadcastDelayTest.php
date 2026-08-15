<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppBroadcastDelayTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_delay_adds_random_range_when_enabled(): void
    {
        $service = app(WhatsAppSettingsService::class);
        $service->updateSettings([
            'delay_between_messages' => 3,
            'random_delay_enabled' => true,
            'min_delay' => 2,
            'max_delay' => 5,
        ]);

        $delay = $service->calculateDelay();

        $this->assertGreaterThanOrEqual(5, $delay);
        $this->assertLessThanOrEqual(8, $delay);
    }

    public function test_calculate_delay_uses_base_only_when_random_disabled(): void
    {
        $service = app(WhatsAppSettingsService::class);
        $service->updateSettings([
            'delay_between_messages' => 4,
            'random_delay_enabled' => false,
            'min_delay' => 2,
            'max_delay' => 5,
        ]);

        $this->assertSame(4, $service->calculateDelay());
    }
}
