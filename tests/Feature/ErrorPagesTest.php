<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Arabic error pages.
 *
 * Before these existed a visitor to a bad URL got Laravel's English screen inside an
 * Arabic RTL product. The views are Blade and inline their own styles on purpose: an
 * error page is exactly the moment the asset pipeline may be unavailable, and a 500
 * page that itself fails to render tells the visitor nothing.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_renders_the_arabic_404(): void
    {
        $response = $this->get('/definitely-not-a-real-url');

        $response->assertNotFound();
        $response->assertSee('الصفحة غير موجودة', escape: false);
        $response->assertSee('dir="rtl"', escape: false);
    }

    public function test_the_404_does_not_leak_the_framework_default(): void
    {
        $this->get('/nope')->assertDontSee('Not Found', escape: false);
    }

    public function test_a_forbidden_page_renders_the_arabic_403(): void
    {
        Route::get('/__test_forbidden', fn () => abort(403))->middleware('web');

        $response = $this->get('/__test_forbidden');

        $response->assertForbidden();
        $response->assertSee('لا تملك صلاحية الوصول', escape: false);
    }

    public function test_every_shipped_error_view_renders(): void
    {
        // A broken @extends or a typo in one of them would otherwise only be found by
        // the visitor who triggers that specific status.
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString((string) $code, $html);
            $this->assertStringContainsString('dir="rtl"', $html);
            $this->assertStringContainsString('العودة للرئيسية', $html);
        }
    }

    public function test_a_signed_in_user_is_offered_the_dashboard(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/nope')->getContent();

        $this->assertStringContainsString(route('admin.dashboard'), $html);
    }

    public function test_a_guest_is_not_offered_the_dashboard(): void
    {
        // The link would only bounce them to the login screen.
        $this->get('/nope')->assertDontSee(route('admin.dashboard'), escape: false);
    }

    public function test_api_requests_still_receive_json_not_html(): void
    {
        $response = $this->getJson('/api/v1/blog/posts/does-not-exist');

        // The error views must not hijack the API: a consumer expecting JSON getting a
        // page of Arabic HTML is a far worse failure than a plain 404 body.
        $response->assertNotFound();
        $response->assertHeader('content-type', 'application/json');
        $this->assertJson($response->getContent());
    }
}
