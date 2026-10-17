<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Frontend\CourseCatalog;
use App\Services\Seo\SeoPage;
use App\Services\Seo\StructuredData;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly CourseCatalog $catalog) {}

    public function index(): View
    {
        $categories = $this->catalog->rootCategories();
        $visible = $this->catalog->visible();

        $seo = SeoPage::make('تصنيفات الدورات')
            ->description('تصفّح كل تصنيفات الدورات: '.$categories->pluck('name')->implode('، ').'.')
            ->canonical(route('categories'))
            ->breadcrumbs(['التصنيفات' => route('categories')])
            ->schema(StructuredData::itemList(
                $categories->map(fn ($category) => [
                    'name' => $category->name,
                    'url' => route('courses', ['category' => $category->slug]),
                ]),
                route('categories'),
            ));

        return view('frontend.pages.categories', [
            'seo' => $seo,
            'categories' => $categories,
            'stats' => [
                'total_categories' => $categories->count(),
                'total_courses' => (clone $visible)->count(),
                'total_instructors' => (clone $visible)->whereNotNull('instructor_id')->distinct()->count('instructor_id'),
            ],
        ]);
    }
}
