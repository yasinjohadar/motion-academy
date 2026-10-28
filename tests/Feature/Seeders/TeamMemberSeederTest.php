<?php

namespace Tests\Feature\Seeders;

use App\Models\TeamMember;
use App\Services\Frontend\TeamSettings;
use Database\Seeders\TeamMemberSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_five_original_members_in_order(): void
    {
        $this->seed(TeamMemberSeeder::class);

        $members = TeamMember::query()->active()->orderBy('sort_order')->get();

        $this->assertCount(5, $members);
        $this->assertSame('م. أحمد سعيد', $members->first()->name);
        $this->assertSame('5.0', $members->last()->rating);
        $this->assertCount(2, $members->first()->socialButtons());
        $this->assertSame('slider', app(TeamSettings::class)->all()['layout']);
    }

    public function test_it_is_idempotent_and_keeps_saved_settings(): void
    {
        $this->seed(TeamMemberSeeder::class);
        app(TeamSettings::class)->save(['columns' => 3]);

        $this->seed(TeamMemberSeeder::class);

        $this->assertSame(5, TeamMember::query()->count());
        $this->assertSame(3, app(TeamSettings::class)->all()['columns']);
    }
}
