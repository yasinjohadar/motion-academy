<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Services\Providers\MetaApiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Asserts the exact wire contract against Meta's documented Cloud API.
 *
 * These prove we send the RIGHT SHAPE. They cannot prove Meta accepts it — there is
 * no live account here, so every call is faked. Real verification happens after the
 * WABA is connected.
 */
class MetaApiProviderTest extends TestCase
{
    protected function provider(): MetaApiProvider
    {
        return new MetaApiProvider([
            'phone_number_id' => '109876543210',
            'access_token' => 'EAAG-test-token',
            'api_version' => 'v21.0',
            'timeout' => 10,
        ]);
    }

    protected function fakeSuccess(string $id = 'wamid.TEST=='): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '966500000000', 'wa_id' => '966500000000']],
                'messages' => [['id' => $id, 'message_status' => 'accepted']],
            ], 200),
        ]);
    }

    public function test_text_hits_the_documented_endpoint_and_method(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendText('+966500000000', 'مرحبا');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://graph.facebook.com/v21.0/109876543210/messages');
    }

    public function test_the_bearer_token_is_sent(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendText('+966500000000', 'مرحبا');

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer EAAG-test-token'));
    }

    public function test_the_text_body_matches_the_documented_shape(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendText('+966500000000', 'مرحبا', previewUrl: true);

        Http::assertSent(function (Request $r): bool {
            $body = $r->data();

            return $body['messaging_product'] === 'whatsapp'
                && $body['recipient_type'] === 'individual'
                && $body['type'] === 'text'
                && $body['text']['body'] === 'مرحبا'
                && $body['text']['preview_url'] === true;
        });
    }

    public function test_the_recipient_is_sent_as_digits_without_a_plus(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendText('+966 50 000 0000', 'x');

        Http::assertSent(fn (Request $r) => $r->data()['to'] === '966500000000');
    }

    public function test_the_message_id_is_extracted_from_the_response(): void
    {
        $this->fakeSuccess('wamid.HBgMOTY2NTAw==');

        $dto = $this->provider()->sendText('+966500000000', 'x');

        $this->assertSame('wamid.HBgMOTY2NTAw==', $dto->metaMessageId);
        $this->assertSame('whatsapp', $dto->rawResponse['messaging_product']);
    }

    public function test_the_template_body_matches_the_documented_shape(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendTemplate('+966500000000', 'otp_code', 'ar', [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '123456']]],
        ]);

        Http::assertSent(function (Request $r): bool {
            $body = $r->data();

            return $body['type'] === 'template'
                && $body['template']['name'] === 'otp_code'
                && $body['template']['language']['code'] === 'ar'
                && $body['template']['components'][0]['parameters'][0]['text'] === '123456';
        });
    }

    public function test_a_template_without_components_omits_the_key(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendTemplate('+966500000000', 'hello_world', 'en_US');

        Http::assertSent(fn (Request $r) => ! array_key_exists('components', $r->data()['template']));
    }

    public function test_the_document_body_matches_the_documented_shape(): void
    {
        $this->fakeSuccess();

        $this->provider()->sendDocument('+966500000000', 'https://x.test/f.pdf', 'ف.pdf', 'وصف');

        Http::assertSent(function (Request $r): bool {
            $body = $r->data();

            return $body['type'] === 'document'
                && $body['document']['link'] === 'https://x.test/f.pdf'
                && $body['document']['filename'] === 'ف.pdf'
                && $body['document']['caption'] === 'وصف';
        });
    }

    // ------------------------------------------------------------------ failures

    public function test_a_meta_error_becomes_a_whatsapp_api_exception_with_the_user_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Generic failure',
                    'code' => 131047,
                    'error_user_msg' => 'انتهت نافذة المحادثة، استخدم قالباً معتمداً.',
                ],
            ], 400),
        ]);

        try {
            $this->provider()->sendText('+966500000000', 'x');
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException $e) {
            // error_user_msg is preferred: it is the actionable text Meta writes for humans.
            $this->assertStringContainsString('انتهت نافذة المحادثة', $e->getMessage());
            $this->assertSame(131047, $e->getCode());
            $this->assertSame(400, $e->getDetails()['status']);
        }
    }

    public function test_a_success_response_without_a_message_id_is_rejected(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp'], 200)]);

        $this->expectException(WhatsAppApiException::class);

        $this->provider()->sendText('+966500000000', 'x');
    }

    public function test_sending_without_configuration_fails_before_any_request(): void
    {
        Http::fake();

        $provider = new MetaApiProvider(['phone_number_id' => '', 'access_token' => '']);

        try {
            $provider->sendText('+966500000000', 'x');
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException) {
            Http::assertNothingSent();
        }
    }

    public function test_the_message_body_is_never_written_to_the_log_on_failure(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'nope', 'code' => 1]], 400)]);

        Log::shouldReceive('channel')->with('whatsapp')->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(
            function (string $message, array $context): bool {
                // Recipient yes, message content no.
                return ! array_key_exists('text', $context)
                    && ! in_array('SECRET-BODY', $context, true);
            }
        );

        try {
            $this->provider()->sendText('+966500000000', 'SECRET-BODY');
        } catch (WhatsAppApiException) {
            // expected
        }
    }

    // ------------------------------------------------------------------ connection

    public function test_test_connection_reports_the_verified_name(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'display_phone_number' => '+966 50 000 0000',
                'verified_name' => 'متجري',
            ], 200),
        ]);

        $result = $this->provider()->testConnection();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('متجري', $result['message']);
    }

    public function test_test_connection_reports_failure_without_throwing(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token', 'code' => 190]], 401),
        ]);

        $result = $this->provider()->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('190', $result['message']);
    }

    public function test_test_connection_reports_missing_configuration(): void
    {
        $result = (new MetaApiProvider([]))->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('غير مكتملة', $result['message']);
    }
}
