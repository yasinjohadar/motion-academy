<?php

namespace Tests\Feature\Admin;

use App\Models\TeamMember;
use App\Models\User;
use App\Services\Frontend\TeamSettings;
use App\Support\SocialPlatforms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.team-members.list',
            'admin.team-members.create',
            'admin.team-members.edit',
            'admin.team-members.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        Storage::fake(config('storage.fallback_disk', 'public'));
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'م. ليان الحسن',
            'title' => 'Motion Designer',
            'bio' => 'مصممة حركة بخبرة عشر سنوات في الإعلانات.',
            'icon' => 'fas fa-user-tie',
            'color' => '#45bec4',
            'rating' => 4.7,
            'profile_url' => '/who-we-are',
            'social_links' => [
                ['platform' => 'behance', 'url' => 'https://www.behance.net/layan'],
                ['platform' => 'email', 'url' => 'layan@example.com'],
            ],
            'is_active' => true,
            'sort_order' => 4,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function settingsPayload(array $overrides = []): array
    {
        return [
            'eyebrow' => '',
            'title' => 'مدرّبونا',
            'subtitle' => 'نخبة من المحترفين',
            'limit' => 3,
            'order' => 'rating',
            'layout' => 'grid',
            'columns' => 3,
            'show_rating' => false,
            'show_social' => true,
            'autoplay' => false,
            'cta_label' => '',
            'cta_url' => '',
            ...$overrides,
        ];
    }

    public function test_admin_sees_members_with_counts_settings_and_platforms(): void
    {
        TeamMember::factory()->count(2)->create();
        TeamMember::factory()->inactive()->create();

        $this->actingAs($this->adminUser())
            ->get(route('admin.team-members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TeamMembers/Index')
                ->has('members.data', 3)
                ->where('counts', ['all' => 3, 'active' => 2])
                ->where('settings.layout', 'slider')
                ->where('settings.columns', 4)
                ->has('platforms', count(SocialPlatforms::ALL)));
    }

    public function test_admin_can_create_a_member_with_avatar_and_social_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.team-members.store'), $this->validPayload([
                'avatar' => UploadedFile::fake()->image('layan.jpg'),
            ]))
            ->assertRedirect(route('admin.team-members.index'));

        $member = TeamMember::query()->sole();
        $this->assertSame('م. ليان الحسن', $member->name);
        $this->assertSame('4.7', $member->rating);
        $this->assertSame([
            ['platform' => 'behance', 'url' => 'https://www.behance.net/layan'],
            ['platform' => 'email', 'url' => 'layan@example.com'],
        ], $member->social_links);
        $this->assertSame($admin->id, $member->created_by);
        Storage::disk(config('storage.fallback_disk', 'public'))->assertExists($member->avatar);
    }

    public function test_update_can_clear_rating_and_social_links_and_keeps_the_avatar(): void
    {
        $member = TeamMember::factory()->create(['avatar' => 'https://cdn.example.com/a.jpg']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.team-members.update', $member), $this->validPayload([
                'rating' => null,
                'social_links' => [],
            ]))
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertNull($member->rating);
        $this->assertSame([], $member->social_links);
        $this->assertSame('https://cdn.example.com/a.jpg', $member->avatar);
    }

    public function test_invalid_member_input_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.team-members.store'), $this->validPayload([
                'name' => '',
                'rating' => 6,
                'color' => 'blue',
                'profile_url' => 'javascript:alert(1)',
                'social_links' => [
                    ['platform' => 'myspace', 'url' => 'https://myspace.com/x'],
                    ['platform' => 'github', 'url' => 'javascript:alert(1)'],
                    ['platform' => 'email', 'url' => 'not-an-email'],
                ],
            ]))
            ->assertSessionHasErrors([
                'name',
                'rating',
                'color',
                'profile_url',
                'social_links.0.platform',
                'social_links.1.url',
                'social_links.2.url',
            ]);

        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_admin_can_toggle_and_delete_a_member(): void
    {
        $admin = $this->adminUser();
        $member = TeamMember::factory()->create();

        $this->actingAs($admin)->post(route('admin.team-members.toggle-active', $member));
        $this->assertFalse($member->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.team-members.destroy', $member));
        $this->assertModelMissing($member);
    }

    public function test_admin_can_save_section_settings_and_hide_the_button(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.team-members.settings'), $this->settingsPayload())
            ->assertSessionHasNoErrors();

        $settings = app(TeamSettings::class)->all();
        $this->assertSame('مدرّبونا', $settings['title']);
        $this->assertSame('', $settings['eyebrow']);
        $this->assertSame('', $settings['cta_label']);
        $this->assertSame('grid', $settings['layout']);
        $this->assertSame(3, $settings['limit']);
    }

    public function test_invalid_section_settings_are_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.team-members.settings'), $this->settingsPayload([
                'title' => '',
                'columns' => 6,
                'order' => 'alphabetical',
                'cta_label' => 'بلا رابط',
                'cta_url' => '',
            ]))
            ->assertSessionHasErrors(['title', 'columns', 'order', 'cta_url']);
    }

    public function test_user_without_permission_cannot_manage_the_team(): void
    {
        $user = $this->panelUserWithoutPermissions();
        $member = TeamMember::factory()->create();

        $this->actingAs($user)->get(route('admin.team-members.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.team-members.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->put(route('admin.team-members.settings'), $this->settingsPayload())->assertForbidden();
        $this->actingAs($user)->delete(route('admin.team-members.destroy', $member))->assertForbidden();

        $this->assertModelExists($member);
    }

    public function test_home_page_shows_the_configured_members_in_order(): void
    {
        TeamMember::factory()->create(['name' => 'الثاني', 'sort_order' => 2]);
        TeamMember::factory()->create(['name' => 'الأول', 'sort_order' => 1]);
        TeamMember::factory()->create(['name' => 'الثالث', 'sort_order' => 3]);
        TeamMember::factory()->inactive()->create(['name' => 'مخفي']);
        app(TeamSettings::class)->save(['limit' => 2, 'title' => 'مدرّبونا']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('مدرّبونا')
            ->assertSeeInOrder(['الأول', 'الثاني'])
            ->assertDontSee('الثالث')
            ->assertDontSee('مخفي');
    }

    public function test_cards_render_rating_social_links_and_cta_from_settings(): void
    {
        TeamMember::factory()->create([
            'rating' => 4.8,
            'social_links' => [
                ['platform' => 'github', 'url' => 'https://github.com/omar'],
                ['platform' => 'email', 'url' => 'omar@example.com'],
            ],
        ]);

        $this->get(route('home'))
            ->assertSee('<span class="team-rating-value">4.8</span>', false)
            ->assertSee('href="https://github.com/omar"', false)
            ->assertSee('href="mailto:omar@example.com"', false)
            ->assertSee('class="swiper team-swiper"', false)
            ->assertSee('تعرف على كامل الفريق');

        app(TeamSettings::class)->save([
            'show_rating' => false,
            'show_social' => false,
            'layout' => 'grid',
            'cta_label' => '',
            'cta_url' => '',
        ]);

        $this->get(route('home'))
            ->assertDontSee('team-rating-value', false)
            ->assertDontSee('https://github.com/omar', false)
            ->assertDontSee('class="swiper team-swiper"', false)
            ->assertDontSee('تعرف على كامل الفريق');
    }

    public function test_section_is_left_out_when_no_member_is_active(): void
    {
        TeamMember::factory()->inactive()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="team-section', false);
    }
}
