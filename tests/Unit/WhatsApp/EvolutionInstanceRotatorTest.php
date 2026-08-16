<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Services\Evolution\EvolutionInstanceRotator;
use App\Modules\WhatsApp\Services\Evolution\EvolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EvolutionInstanceRotatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->mock(EvolutionService::class, function ($mock) {
            $mock->shouldReceive('refreshRotationCandidates')->andReturn(0);
        });
    }

    public function test_lru_cycles_through_open_instances(): void
    {
        EvolutionInstance::create([
            'instance_name' => 'inst-a',
            'connection_status' => 'open',
            'rotation_enabled' => true,
        ]);
        EvolutionInstance::create([
            'instance_name' => 'inst-b',
            'connection_status' => 'open',
            'rotation_enabled' => true,
        ]);
        EvolutionInstance::create([
            'instance_name' => 'inst-c',
            'connection_status' => 'close',
            'rotation_enabled' => true,
        ]);

        $rotator = app(EvolutionInstanceRotator::class);

        $first = $rotator->nextInstance()->instance_name;
        $rotator->markUsed(EvolutionInstance::where('instance_name', $first)->firstOrFail());
        $second = $rotator->nextInstance()->instance_name;

        $this->assertContains($first, ['inst-a', 'inst-b']);
        $this->assertContains($second, ['inst-a', 'inst-b']);
        $this->assertNotSame($first, $second);
        $this->assertSame(2, $rotator->poolCount());
    }

    public function test_rotation_disabled_instances_are_excluded(): void
    {
        EvolutionInstance::create([
            'instance_name' => 'inst-a',
            'connection_status' => 'open',
            'rotation_enabled' => false,
        ]);
        EvolutionInstance::create([
            'instance_name' => 'inst-b',
            'connection_status' => 'open',
            'rotation_enabled' => true,
        ]);

        $rotator = app(EvolutionInstanceRotator::class);

        $this->assertSame(1, $rotator->poolCount());
        $this->assertSame('inst-b', $rotator->nextInstance()->instance_name);
    }
}
