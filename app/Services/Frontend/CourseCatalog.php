<?php

namespace App\Services\Frontend;

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The public course catalogue: which courses a visitor can see, and how the
 * courses page filters and sorts them (search, category, level, price, sort).
 *
 * The page and its AJAX endpoint (assets/js/courses.js) both go through here, so
 * the first render and every later refresh agree.
 */
class CourseCatalog
{
    public const SORTS = ['popular', 'newest', 'price-asc', 'price-desc'];

    public const PRICING = ['free', 'sale', 'paid'];

    /**
     * @return array{search: string, categories: array<int, string>, levels: array<int, string>, price_max: float|null, pricing: string, sort: string}
     */
    public function filtersFromRequest(Request $request): array
    {
        $categories = array_values(array_filter(array_map(
            'strval',
            (array) $request->input('categories', $request->filled('category') ? [$request->input('category')] : []),
        )));

        $levels = array_values(array_intersect(
            array_map('strval', (array) $request->input('levels', [])),
            CourseLevel::values(),
        ));

        $sort = (string) $request->input('sort', 'popular');
        $pricing = (string) $request->input('pricing', '');

        return [
            'search' => trim((string) $request->input('search', '')),
            'categories' => $categories,
            'levels' => $levels,
            'price_max' => is_numeric($request->input('price_max')) ? (float) $request->input('price_max') : null,
            'pricing' => in_array($pricing, self::PRICING, true) ? $pricing : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'popular',
        ];
    }

    /**
     * Published courses in active categories — everything a visitor may see.
     *
     * @return Builder<Course>
     */
    public function visible(): Builder
    {
        return Course::query()
            ->published()
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->with(['category', 'instructor:id,name']);
    }

    /**
     * @param  array{search: string, categories: array<int, string>, levels: array<int, string>, price_max: float|null, pricing: string, sort: string}  $filters
     * @return Builder<Course>
     */
    public function query(array $filters): Builder
    {
        $query = $this->visible()
            ->when($filters['search'] !== '', fn (Builder $q) => $q->search($filters['search']));

        if ($filters['categories'] !== []) {
            $query->whereIn('course_category_id', $this->categoryIds($filters['categories']));
        }

        if ($filters['levels'] !== []) {
            // "All levels" courses suit whichever level the visitor picked.
            $query->whereIn('level', [...$filters['levels'], CourseLevel::All->value]);
        }

        if ($filters['price_max'] !== null) {
            // `? + 0`: PDO binds the ceiling as text, and SQLite compares an untyped CASE
            // result against text as a string. The addition makes both sides numeric.
            $query->whereRaw($this->effectivePriceSql().' <= (? + 0)', [...$this->effectivePriceBindings(), $filters['price_max']]);
        }

        match ($filters['pricing'] ?? '') {
            'free' => $query->where('is_free', true),
            'paid' => $query->where('is_free', false),
            'sale' => $query->where('is_free', false)
                ->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'price')
                ->where(fn (Builder $q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now())),
            default => null,
        };

        return match ($filters['sort']) {
            'newest' => $query->latest('published_at')->latest('id'),
            'price-asc' => $query->orderByRaw($this->effectivePriceSql().' asc', $this->effectivePriceBindings())->latest('id'),
            'price-desc' => $query->orderByRaw($this->effectivePriceSql().' desc', $this->effectivePriceBindings())->latest('id'),
            default => $query->orderByDesc('is_featured')->latest('published_at')->latest('id'),
        };
    }

    /**
     * @param  array{search: string, categories: array<int, string>, levels: array<int, string>, price_max: float|null, pricing: string, sort: string}  $filters
     * @return LengthAwarePaginator<int, Course>
     */
    public function paginate(array $filters, int $perPage = 9): LengthAwarePaginator
    {
        return $this->query($filters)->paginate(max(1, min($perPage, 48)))->withQueryString();
    }

    /**
     * @param  array{search: string, categories: array<int, string>, levels: array<int, string>, price_max: float|null, pricing: string, sort: string}  $filters
     * @return Collection<int, Course>
     */
    public function limit(array $filters, int $limit): Collection
    {
        return $this->query($filters)->limit($limit)->get();
    }

    /**
     * Upper bound for the price slider, rounded up so every course fits under it.
     */
    public function maxPrice(): int
    {
        $max = (float) $this->visible()->where('is_free', false)->max('price');

        return max(10, (int) ceil($max / 10) * 10);
    }

    /**
     * Active root categories with their visible course counts (children included).
     *
     * @return Collection<int, CourseCategory>
     */
    public function rootCategories(?int $limit = null): Collection
    {
        $categories = CourseCategory::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->active()])
            ->orderByDesc('is_featured')
            ->orderBy('order')
            ->orderBy('name')
            ->when($limit, fn (Builder $q) => $q->limit($limit))
            ->get();

        $counts = $this->visible()
            ->toBase()
            ->selectRaw('course_category_id, count(*) as aggregate')
            ->groupBy('course_category_id')
            ->pluck('aggregate', 'course_category_id');

        return $categories->each(function (CourseCategory $root) use ($counts): void {
            $ids = [$root->id, ...$root->children->modelKeys()];
            $root->setAttribute('courses_count', (int) collect($ids)->sum(fn (int $id) => $counts[$id] ?? 0));
        });
    }

    /**
     * Expand category slugs to ids; a root category brings its sub-categories along.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, int>
     */
    private function categoryIds(array $slugs): array
    {
        $ids = CourseCategory::query()->active()->whereIn('slug', $slugs)->pluck('id')->all();

        if ($ids === []) {
            return [0];
        }

        return CourseCategory::query()
            ->active()
            ->where(fn (Builder $q) => $q->whereIn('id', $ids)->orWhereIn('parent_id', $ids))
            ->pluck('id')
            ->all();
    }

    /**
     * SQL for the price a student pays now; mirrors Course::effectivePrice().
     */
    private function effectivePriceSql(): string
    {
        return '(CASE WHEN is_free = 1 THEN 0 '
            .'WHEN sale_price IS NOT NULL AND sale_price < price AND (sale_ends_at IS NULL OR sale_ends_at > ?) THEN sale_price '
            .'ELSE price END)';
    }

    /**
     * @return array<int, string>
     */
    private function effectivePriceBindings(): array
    {
        return [now()->toDateTimeString()];
    }
}
