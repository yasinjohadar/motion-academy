<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\ExceptionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExceptionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_context_carries_url_method_and_ip(): void
    {
        $request = Request::create('https://example.test/admin/users?page=2', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.9',
        ]);

        $context = ExceptionContext::forRequest($request);

        $this->assertSame('https://example.test/admin/users?page=2', $context['url']);
        $this->assertSame('POST', $context['method']);
        $this->assertSame('203.0.113.9', $context['ip']);
    }

    public function test_request_context_includes_the_authenticated_user_email(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.test']);

        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->assertSame('operator@example.test', ExceptionContext::forRequest($request)['user_email']);
    }

    public function test_request_context_omits_the_user_email_for_guests(): void
    {
        $context = ExceptionContext::forRequest(Request::create('/login', 'GET'));

        $this->assertArrayNotHasKey('user_email', $context);
        $this->assertArrayHasKey('url', $context);
    }

    public function test_console_context_reports_the_command_line(): void
    {
        $context = ExceptionContext::forConsole(['artisan', 'queue:work', '--queue=mail']);

        $this->assertSame('queue:work --queue=mail', $context['console_command']);
    }

    public function test_console_context_is_empty_without_arguments(): void
    {
        $this->assertSame([], ExceptionContext::forConsole(['artisan']));
    }

    public function test_console_context_never_reports_a_synthesised_url(): void
    {
        // Symfony fills REQUEST_METHOD/REMOTE_ADDR/HTTP_HOST under CLI, so a
        // server-variable check would leak a fake "http://localhost" here.
        $context = ExceptionContext::forConsole(['artisan', 'backup:run-scheduled']);

        $this->assertArrayNotHasKey('url', $context);
        $this->assertArrayNotHasKey('ip', $context);
    }
}
