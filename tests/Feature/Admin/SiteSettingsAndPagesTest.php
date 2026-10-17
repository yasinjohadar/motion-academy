<?php

namespace Tests\Feature\Admin;

use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteSettingsAndPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.site-settings.list',
            'admin.site-settings.edit',
            'admin.site-pages.list',
            'admin.site-pages.create',
            'admin.site-pages.edit',
            'admin.site-pages.delete',
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

    public function test_admin_can_view_site_settings(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.site-settings.edit'))
            ->assertOk();
    }

    public function test_admin_can_update_site_settings_with_social_links(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.site-settings.update'), [
                'site_name' => 'أكاديمية الاختبار',
                'site_name_en' => 'Test Academy',
                'primary_whatsapp' => '966500000000',
                'email' => 'info@example.com',
                'social_links' => [
                    [
                        'label' => 'إنستغرام',
                        'url' => 'https://instagram.com/test',
                        'icon' => 'ri-instagram-line',
                        'sort_order' => 0,
                        'is_active' => true,
                    ],
                    [
                        'label' => 'تيليجرام',
                        'url' => 'https://t.me/test',
                        'icon' => 'ri-telegram-line',
                        'sort_order' => 1,
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.site-settings.edit'));

        $this->assertDatabaseHas('site_settings', [
            'site_name' => 'أكاديمية الاختبار',
            'primary_whatsapp' => '966500000000',
        ]);
        $this->assertDatabaseCount('social_links', 2);
        $this->assertSame(1, SiteSetting::query()->count());
        $this->assertTrue(SocialLink::query()->where('label', 'إنستغرام')->exists());
    }

    public function test_admin_can_set_site_wide_seo(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.site-settings.update'), [
                'site_name' => 'Motion Academy',
                'seo_title' => 'Motion Academy — تعلّم التحريك',
                'seo_description' => 'دورات تحريك عملية.',
                'seo_keywords' => 'تحريك, موشن',
                'seo_og_image' => 'https://cdn.example.com/og.png',
                'twitter_handle' => 'MotionAcademy',
                // Pasted as the whole tag, the way Search Console hands it out.
                'google_site_verification' => '<meta name="google-site-verification" content="abc123XYZ" />',
                'bing_site_verification' => 'BING-CODE',
                'allow_indexing' => '0',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.site-settings.edit'));

        $setting = SiteSetting::current();
        $this->assertSame('Motion Academy — تعلّم التحريك', $setting->seo_title);
        $this->assertSame('@MotionAcademy', $setting->twitter_handle);
        $this->assertSame('abc123XYZ', $setting->google_site_verification);
        $this->assertSame('BING-CODE', $setting->bing_site_verification);
        $this->assertFalse($setting->allow_indexing);

        $this->actingAs($this->adminUser())
            ->get(route('admin.site-settings.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('setting.seo_description', 'دورات تحريك عملية.')
                ->where('setting.allow_indexing', false));
    }

    public function test_seo_settings_are_validated(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.site-settings.update'), [
                'site_name' => 'Motion Academy',
                'twitter_handle' => 'not a handle!',
                'seo_og_image' => 'javascript:alert(1)',
                'seo_title' => str_repeat('ع', 121),
            ])
            ->assertSessionHasErrors(['twitter_handle', 'seo_og_image', 'seo_title']);
    }

    public function test_admin_can_manage_site_pages(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('admin.site-pages.store'), [
                'title' => 'من نحن',
                'slug' => 'about-us',
                'excerpt' => 'نبذة',
                'body' => 'محتوى الصفحة',
                'is_published' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.site-pages.index'));

        $page = SitePage::query()->first();
        $this->assertNotNull($page);
        $this->assertSame('about-us', $page->slug);

        $this->actingAs($admin)
            ->get(route('admin.site-pages.index'))
            ->assertOk();
    }
}
