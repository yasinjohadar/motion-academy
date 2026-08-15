<?php

namespace Tests\Feature\WhatsApp;

use App\Exceptions\WhatsAppApiException;
use App\Services\WhatsApp\Providers\FlaxxaApiProvider;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Everything here is Http::fake. It proves we send the shape Flaxxa documents —
 * it cannot prove Flaxxa accepts it. That needs a real token.
 */
class FlaxxaApiProviderTest extends TestCase
{
    protected function provider(array $overrides = []): FlaxxaApiProvider
    {
        return new FlaxxaApiProvider(array_merge([
            'base_url' => 'https://wapi.flaxxa.com',
            'token' => 'brand-token',
            'timeout' => 5,
        ], $overrides));
    }

    protected function fakeSuccess(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'message_id' => 28341,
                'message_wamid' => 'wamid.HBgM123',
            ]),
        ]);
    }

    // ------------------------------------------------------------------ request shape

    public function test_a_text_send_posts_the_documented_json_with_the_token_in_the_body(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendText('+918470968883', 'Your order is confirmed.');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://wapi.flaxxa.com/api/v1/sendmessage'
                && $request->method() === 'POST'
                // On JSON endpoints Flaxxa reads the token from the body, not a header.
                && $request['token'] === 'brand-token'
                && $request['phone'] === '+918470968883'
                && $request['message'] === 'Your order is confirmed.';
        });
    }

    public function test_a_template_send_passes_meta_components_straight_through(): void
    {
        $this->fakeSuccess();

        $components = [[
            'type' => 'body',
            'parameters' => [['type' => 'text', 'text' => '123456']],
        ]];

        $this->provider()->sendTemplate('+918470968883', 'otp_code', 'ar', $components);

        Http::assertSent(function (Request $request) use ($components): bool {
            return $request->url() === 'https://wapi.flaxxa.com/api/v1/sendtemplatemessage'
                && $request['template_name'] === 'otp_code'
                && $request['template_language'] === 'ar'
                && $request['components'] === $components;
        });
    }

    public function test_a_template_without_variables_omits_the_components_key(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendTemplate('+918470968883', 'plain_notice', 'en_US');

        Http::assertSent(fn (Request $request): bool => ! array_key_exists('components', $request->data()));
    }

    public function test_the_template_endpoint_is_queried_with_the_token_in_the_query_string(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'templates' => []])]);

        $this->provider()->listTemplates();

        Http::assertSent(function (Request $request): bool {
            // GET endpoints take the token as ?token=, not in a body.
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://wapi.flaxxa.com/api/v1/getTemplates')
                && str_contains($request->url(), 'token=brand-token');
        });
    }

    public function test_a_trailing_slash_on_the_base_url_does_not_double_up(): void
    {
        $this->fakeSuccess();

        $this->provider(['base_url' => 'https://wapi.flaxxa.com/'])->sendText('+918470968883', 'hi');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://wapi.flaxxa.com/api/v1/sendmessage');
    }

    public function test_the_recipient_is_normalised_to_e164(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendText('00918470968883', 'hi');

        Http::assertSent(fn (Request $request): bool => $request['phone'] === '+918470968883');
    }

    // ------------------------------------------------------------------ response handling

    public function test_the_wamid_becomes_the_message_id(): void
    {
        $this->fakeSuccess();

        $dto = $this->provider()->sendText('+918470968883', 'hi');

        // The wamid is Meta's own id, so it lands in the same column as a direct send.
        $this->assertSame('wamid.HBgM123', $dto->metaMessageId);
    }

    public function test_the_flaxxa_numeric_id_is_kept_for_status_polling(): void
    {
        $this->fakeSuccess();

        $dto = $this->provider()->sendText('+918470968883', 'hi');

        $this->assertSame(28341, $dto->rawResponse['message_id']);
    }

    /**
     * The trap this whole provider is shaped around.
     */
    public function test_a_200_carrying_status_error_is_treated_as_a_failure(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'error', 'message' => 'Invalid API Token.'], 200),
        ]);

        $this->expectException(WhatsAppApiException::class);
        $this->expectExceptionMessage('Invalid API Token.');

        // $response->failed() is false here — only the body reveals the failure.
        $this->provider()->sendText('+918470968883', 'hi');
    }

    public function test_a_response_without_a_wamid_is_rejected_rather_than_recorded_as_sent(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'])]);

        $this->expectException(WhatsAppApiException::class);
        $this->expectExceptionMessage('لا تحتوي على معرّف رسالة');

        $this->provider()->sendText('+918470968883', 'hi');
    }

    public function test_an_http_error_surfaces_the_provider_message(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid API Token.'], 401)]);

        $this->expectExceptionMessage('Invalid API Token.');

        $this->provider()->sendText('+918470968883', 'hi');
    }

    public function test_field_validation_errors_are_unwrapped(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'error', 'errors' => ['token' => ['The token field is required.']]], 400),
        ]);

        $this->expectExceptionMessage('The token field is required.');

        $this->provider()->sendText('+918470968883', 'hi');
    }

    public function test_rate_limiting_gets_an_actionable_message(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $this->expectExceptionMessage('60 طلباً في الدقيقة');

        $this->provider()->sendText('+918470968883', 'hi');
    }

    public function test_a_failure_is_logged_without_the_message_body(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Nope'], 400)]);

        $logged = [];
        Log::shouldReceive('channel')->with('whatsapp')->andReturnSelf();
        Log::shouldReceive('error')->andReturnUsing(function ($message, $context) use (&$logged) {
            $logged[] = $context;
        });

        try {
            $this->provider()->sendText('+918470968883', 'Patient record 12345');
        } catch (WhatsAppApiException) {
            // expected
        }

        $encoded = json_encode($logged, JSON_UNESCAPED_UNICODE);

        // Message bodies can carry personal data; logs are read far more widely.
        $this->assertStringNotContainsString('Patient record 12345', $encoded);
        $this->assertStringContainsString('918470968883', $encoded);
    }

    // ------------------------------------------------------------------ configuration

    public function test_sending_without_a_token_fails_before_any_request(): void
    {
        Http::fake();

        try {
            $this->provider(['token' => ''])->sendText('+918470968883', 'hi');
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException $e) {
            $this->assertStringContainsString('غير مكتملة', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_is_configured_requires_both_url_and_token(): void
    {
        $this->assertTrue($this->provider()->isConfigured());
        $this->assertFalse($this->provider(['token' => ''])->isConfigured());
        $this->assertFalse($this->provider(['base_url' => ''])->isConfigured());
    }

    // ------------------------------------------------------------------ templates

    public function test_only_approved_templates_are_offered(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'templates' => [
                ['name' => 'otp_code', 'status' => 'APPROVED', 'language' => 'ar', 'category' => 'AUTHENTICATION'],
                ['name' => 'promo', 'status' => 'PENDING', 'language' => 'ar', 'category' => 'MARKETING'],
                ['name' => 'old', 'status' => 'REJECTED', 'language' => 'ar', 'category' => 'MARKETING'],
            ]]),
        ]);

        $templates = $this->provider()->listTemplates();

        // A pending template cannot be sent, so offering it would only produce a
        // failure at send time.
        $this->assertCount(1, $templates);
        $this->assertSame('otp_code', $templates[0]['name']);
    }

    public function test_the_connection_test_reports_the_approved_template_count(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'templates' => [
                ['name' => 'a', 'status' => 'APPROVED'],
                ['name' => 'b', 'status' => 'APPROVED'],
            ]]),
        ]);

        $result = $this->provider()->testConnection();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('2', $result['message']);
    }

    public function test_the_connection_test_fails_cleanly_on_a_bad_token(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid API Token.'], 401)]);

        $result = $this->provider()->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid API Token.', $result['message']);
    }

    // ------------------------------------------------------------------ attachments

    public function test_a_document_is_downloaded_then_uploaded_as_multipart(): void
    {
        Http::fake([
            'https://files.example.com/*' => Http::response('PDFBYTES'),
            'https://wapi.flaxxa.com/*' => Http::response([
                'status' => 'success', 'message_id' => 1, 'message_wamid' => 'wamid.X',
            ]),
        ]);

        $this->provider()->sendDocument('+918470968883', 'https://files.example.com/a.pdf', 'invoice.pdf', 'فاتورتك');

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://wapi.flaxxa.com/api/v1/sendmessagewithattachment') {
                return false;
            }

            $body = (string) $request->body();

            // Flaxxa accepts no document URL, so the bytes themselves must be forwarded.
            return $request->isMultipart()
                && str_contains($body, 'PDFBYTES')
                && str_contains($body, 'invoice.pdf')
                && str_contains($body, 'brand-token');
        });
    }

    public function test_an_oversized_attachment_is_refused_before_upload(): void
    {
        config()->set('whatsapp.flaxxa.max_attachment_bytes', 10);

        Http::fake([
            'https://files.example.com/*' => Http::response(str_repeat('x', 50)),
            'https://wapi.flaxxa.com/*' => Http::response(['status' => 'success']),
        ]);

        try {
            $this->provider()->sendDocument('+918470968883', 'https://files.example.com/big.pdf', 'big.pdf');
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException $e) {
            $this->assertStringContainsString('يتجاوز الحد', $e->getMessage());
        }

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'wapi.flaxxa.com'));
    }

    public function test_an_unreachable_attachment_url_gives_a_clear_error(): void
    {
        Http::fake([
            'https://files.example.com/*' => Http::response('', 404),
            'https://wapi.flaxxa.com/*' => Http::response(['status' => 'success']),
        ]);

        $this->expectExceptionMessage('تعذّر تنزيل المرفق');

        $this->provider()->sendDocument('+918470968883', 'https://files.example.com/gone.pdf', 'gone.pdf');
    }

    // ------------------------------------------------------------------ status

    public function test_status_is_fetched_by_the_flaxxa_numeric_id(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => [
                'id' => 28341, 'value' => 'delivered', 'error_details' => null,
            ]]),
        ]);

        $status = $this->provider()->fetchStatus(28341);

        $this->assertSame('delivered', $status['value']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://wapi.flaxxa.com/api/v1/get_message_response'
                // Their numeric id, not the wamid — only Flaxxa understands it.
                && $request['message_id'] === 28341
                && $request['token'] === 'brand-token';
        });
    }

    // ------------------------------------------------------------------ factory

    public function test_the_factory_builds_the_flaxxa_provider(): void
    {
        $this->assertInstanceOf(
            FlaxxaApiProvider::class,
            WhatsAppProviderFactory::create('flaxxa', ['base_url' => 'https://x', 'token' => 't'])
        );
    }
}
