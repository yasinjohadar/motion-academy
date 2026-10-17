<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\Seo\SeoPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Newsletter sign-up from the public site (assets/js/newsletter.js) and the
 * unsubscribe page every email links to.
 */
class NewsletterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // The app only renders exceptions as JSON under api/*, and newsletter.js reads
        // JSON — so validation errors are answered here rather than thrown.
        $validator = Validator::make(
            $request->only('email', 'source'),
            [
                'email' => ['required', 'email:rfc', 'max:255'],
                'source' => ['nullable', 'string', 'max:32'],
            ],
            [],
            ['email' => 'البريد الإلكتروني'],
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('email'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = Str::lower(trim((string) $request->input('email')));
        $source = $this->source($request->input('source'));
        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber?->isSubscribed()) {
            return response()->json([
                'success' => true,
                'message' => 'بريدك مشترك بالفعل في القائمة البريدية — شكراً لك!',
            ]);
        }

        if ($subscriber) {
            $subscriber->subscribe($source);

            return response()->json([
                'success' => true,
                'message' => 'أهلاً بعودتك! تم تفعيل اشتراكك من جديد.',
            ]);
        }

        NewsletterSubscriber::query()->create([
            'email' => $email,
            'source' => $source,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الاشتراك بنجاح! سيصلك جديد الدورات والمقالات أولاً بأول.',
        ], 201);
    }

    /**
     * Asks before unsubscribing: mail scanners open links, and a GET that changed
     * state would unsubscribe people who never clicked.
     */
    public function unsubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        return view('frontend.pages.newsletter-unsubscribe', ['subscriber' => $subscriber, 'state' => 'confirm', 'seo' => SeoPage::make('إلغاء الاشتراك')->noindex(follow: false)]);
    }

    public function confirmUnsubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        if ($subscriber->isSubscribed()) {
            $subscriber->unsubscribe();
        }

        return view('frontend.pages.newsletter-unsubscribe', ['subscriber' => $subscriber, 'state' => 'unsubscribed', 'seo' => SeoPage::make('إلغاء الاشتراك')->noindex(follow: false)]);
    }

    public function resubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        if (! $subscriber->isSubscribed()) {
            $subscriber->subscribe();
        }

        return view('frontend.pages.newsletter-unsubscribe', ['subscriber' => $subscriber, 'state' => 'resubscribed', 'seo' => SeoPage::make('إعادة الاشتراك')->noindex(follow: false)]);
    }

    private function source(mixed $source): string
    {
        $source = (string) $source;

        // "admin" is reserved for addresses added from the control panel.
        return array_key_exists($source, NewsletterSubscriber::SOURCES) && $source !== 'admin' ? $source : 'website';
    }
}
