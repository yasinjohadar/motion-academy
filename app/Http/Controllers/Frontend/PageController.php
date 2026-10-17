<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Frontend\CourseCatalog;
use App\Services\Seo\SeoPage;
use App\Support\AccountType;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Content pages of the public site. Cart and checkout are design-only until
 * orders and payments exist.
 */
class PageController extends Controller
{
    public function about(): View
    {
        return view('frontend.pages.about', [
            'seo' => SeoPage::make('حول الأكاديمية')->canonical(route('about'))->breadcrumbs(['حول الأكاديمية' => route('about')]),
        ]);
    }

    /**
     * "Who we are": the academy's idea, told as a motion timeline, with real
     * numbers, the fields we teach, and the people who teach them.
     */
    public function whoWeAre(CourseCatalog $catalog): View
    {
        $visible = $catalog->visible();

        return view('frontend.pages.who-we-are', [
            'seo' => SeoPage::make('من نحن')
                ->description('أكاديمية عربية تعلّمك التحريك وصناعة المحتوى البصري بالتطبيق: من الفكرة إلى الرسم إلى الحركة.')
                ->canonical(route('who-we-are'))
                ->breadcrumbs(['من نحن' => route('who-we-are')]),
            'stats' => [
                'courses' => (clone $visible)->count(),
                'lessons' => (int) (clone $visible)->sum('lessons_count'),
                'hours' => (int) ceil((clone $visible)->sum('duration_seconds') / 3600),
                // whereHas, not User::role(): that throws before the role exists.
                'students' => User::query()->whereHas('roles', fn ($roles) => $roles->where('name', AccountType::STUDENT))->count(),
            ],
            'categories' => $catalog->rootCategories(limit: 8),
            'instructors' => $this->instructors($catalog),
        ]);
    }

    /**
     * Everyone who teaches a published course, with how many.
     *
     * @return Collection<int, array{name: string, initial: string, courses: int}>
     */
    private function instructors(CourseCatalog $catalog): Collection
    {
        $counts = $catalog->visible()
            ->toBase()
            ->whereNotNull('instructor_id')
            ->selectRaw('instructor_id, count(*) as aggregate')
            ->groupBy('instructor_id')
            ->pluck('aggregate', 'instructor_id');

        return User::query()
            ->whereKey($counts->keys())
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'name' => $user->name,
                'initial' => mb_substr(trim($user->name), 0, 1),
                'courses' => (int) $counts[$user->id],
            ])
            ->sortByDesc('courses')
            ->values();
    }

    public function cart(): View
    {
        return view('frontend.pages.cart', ['seo' => SeoPage::make('سلة المشتريات')->noindex()]);
    }

    public function checkout(): View
    {
        return view('frontend.pages.checkout', ['seo' => SeoPage::make('إتمام الشراء')->noindex()]);
    }
}
