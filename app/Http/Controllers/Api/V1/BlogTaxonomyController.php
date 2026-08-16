<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogCategoryResource;
use App\Http\Resources\BlogTagResource;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Categories and tags, so a frontend can build its navigation.
 *
 * Both lists are small and fully public; there is no pagination because a blog with
 * enough categories to need it has a different problem.
 */
class BlogTaxonomyController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        return BlogCategoryResource::collection(
            BlogCategory::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()
        );
    }

    public function tags(): AnonymousResourceCollection
    {
        return BlogTagResource::collection(
            BlogTag::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()
        );
    }
}
