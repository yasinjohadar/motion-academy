<?php

namespace Tests\Feature\Frontend;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_subscribe_and_the_source_is_recorded(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => '  Reader@Example.com ', 'source' => 'home'])
            ->assertCreated()
            ->assertJson(['success' => true]);

        $subscriber = NewsletterSubscriber::query()->sole();
        $this->assertSame('reader@example.com', $subscriber->email);
        $this->assertSame('home', $subscriber->source);
        $this->assertTrue($subscriber->isSubscribed());
        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertSame(48, strlen($subscriber->token));
    }

    public function test_subscribing_twice_does_not_duplicate(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'a@example.com']);
        $this->postJson(route('newsletter.subscribe'), ['email' => 'A@example.com'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, NewsletterSubscriber::query()->count());
    }

    public function test_an_unsubscribed_address_is_reactivated(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'back@example.com']);

        $this->postJson(route('newsletter.subscribe'), ['email' => 'back@example.com', 'source' => 'footer'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $subscriber->refresh();
        $this->assertTrue($subscriber->isSubscribed());
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertSame('footer', $subscriber->source);
    }

    public function test_invalid_email_and_reserved_source_are_handled(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->postJson(route('newsletter.subscribe'), ['email' => 'x@example.com', 'source' => 'admin'])->assertCreated();
        $this->assertSame('website', NewsletterSubscriber::query()->sole()->source);
    }

    public function test_unsubscribe_needs_a_confirmation_click(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        // Opening the link (as mail scanners do) changes nothing.
        $this->get($subscriber->unsubscribeUrl())
            ->assertOk()
            ->assertSee('نعم، ألغِ اشتراكي');
        $this->assertTrue($subscriber->fresh()->isSubscribed());

        $this->post(route('newsletter.unsubscribe.confirm', $subscriber->token))
            ->assertOk()
            ->assertSee('تم إلغاء اشتراكك');
        $this->assertFalse($subscriber->fresh()->isSubscribed());

        $this->post(route('newsletter.resubscribe', $subscriber->token))
            ->assertOk()
            ->assertSee('أهلاً بعودتك');
        $this->assertTrue($subscriber->fresh()->isSubscribed());
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get(route('newsletter.unsubscribe', 'nope'))->assertNotFound();
    }

    public function test_home_and_footer_forms_post_to_the_newsletter(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-source="home"', false)
            ->assertSee('data-source="footer"', false);
    }
}
