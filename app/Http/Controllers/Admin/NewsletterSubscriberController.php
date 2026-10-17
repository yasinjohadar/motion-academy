<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsletterSubscriberStoreRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The newsletter mailing list: who signed up, from where, and whether they are
 * still subscribed. Sign-ups arrive from the public site (Frontend\NewsletterController).
 */
class NewsletterSubscriberController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'newsletter-subscribers',
            false,
            [
                'edit' => ['toggle'],
                'admin.newsletter-subscribers.export' => ['export'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $subscribers = $this->filteredQuery($filters)
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (NewsletterSubscriber $subscriber) => $subscriber->toAdminArray());

        $sources = NewsletterSubscriber::query()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        return Inertia::render('Admin/Newsletter/Index', [
            'subscribers' => $subscribers,
            'filters' => $filters,
            'stats' => [
                'total' => NewsletterSubscriber::query()->count(),
                'subscribed' => NewsletterSubscriber::query()->subscribed()->count(),
                'unsubscribed' => NewsletterSubscriber::query()->where('status', NewsletterSubscriber::STATUS_UNSUBSCRIBED)->count(),
                'this_month' => NewsletterSubscriber::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'sources' => collect(NewsletterSubscriber::SOURCES)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label, 'count' => (int) ($sources[$value] ?? 0)])
                ->filter(fn (array $source) => $source['count'] > 0)
                ->values(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(NewsletterSubscriberStoreRequest $request): RedirectResponse
    {
        NewsletterSubscriber::query()->create([
            ...$request->validated(),
            'source' => 'admin',
        ]);

        return back()->with('success', 'تمت إضافة المشترك إلى القائمة البريدية.');
    }

    public function toggle(NewsletterSubscriber $newsletter_subscriber): RedirectResponse
    {
        if ($newsletter_subscriber->isSubscribed()) {
            $newsletter_subscriber->unsubscribe();

            return back()->with('success', 'تم إلغاء اشتراك '.$newsletter_subscriber->email.'.');
        }

        $newsletter_subscriber->subscribe();

        return back()->with('success', 'تم تفعيل اشتراك '.$newsletter_subscriber->email.'.');
    }

    public function destroy(NewsletterSubscriber $newsletter_subscriber): RedirectResponse
    {
        $newsletter_subscriber->delete();

        return back()->with('success', 'تم حذف المشترك من القائمة البريدية.');
    }

    /**
     * CSV of the current filter, streamed so a large list never sits in memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($this->filters($request))->latest('created_at');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens Arabic text correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', 'name', 'status', 'source', 'subscribed_at', 'unsubscribed_at']);

            $query->chunk(500, function ($subscribers) use ($out): void {
                foreach ($subscribers as $subscriber) {
                    fputcsv($out, [
                        $subscriber->email,
                        $subscriber->name,
                        $subscriber->status,
                        $subscriber->source,
                        $subscriber->subscribed_at?->toDateTimeString(),
                        $subscriber->unsubscribed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{query: string, status: string, source: string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->string('status');
        $source = (string) $request->string('source');

        return [
            'query' => trim((string) $request->string('query')),
            'status' => in_array($status, [NewsletterSubscriber::STATUS_SUBSCRIBED, NewsletterSubscriber::STATUS_UNSUBSCRIBED], true) ? $status : '',
            'source' => array_key_exists($source, NewsletterSubscriber::SOURCES) ? $source : '',
        ];
    }

    /**
     * @param  array{query: string, status: string, source: string}  $filters
     * @return Builder<NewsletterSubscriber>
     */
    private function filteredQuery(array $filters): Builder
    {
        return NewsletterSubscriber::query()
            ->when($filters['query'] !== '', fn (Builder $q) => $q->where(function (Builder $inner) use ($filters) {
                $inner->where('email', 'like', '%'.$filters['query'].'%')
                    ->orWhere('name', 'like', '%'.$filters['query'].'%');
            }))
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['source'] !== '', fn (Builder $q) => $q->where('source', $filters['source']));
    }
}
