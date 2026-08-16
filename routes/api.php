<?php

use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\BlogTaxonomyController;
use App\Modules\WhatsApp\Controllers\Api\EvolutionWebhookController;
use App\Modules\WhatsApp\Controllers\Api\MetaWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API, version 1
|--------------------------------------------------------------------------
| Only the blog is exposed. The admin panel runs on Inertia and needs no REST
| surface, so building one for it would be endpoints nobody calls.
|
| Read-only and unauthenticated: these are published articles anyone can already
| read on the site itself, so a token would protect nothing. The version prefix
| is here from the first endpoint because adding one later breaks every consumer.
*/
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::get('blog/posts', [BlogPostController::class, 'index'])->name('blog.posts.index');
    Route::get('blog/categories', [BlogTaxonomyController::class, 'categories'])->name('blog.categories');
    Route::get('blog/tags', [BlogTaxonomyController::class, 'tags'])->name('blog.tags');

    // Registered last: a bare `{slug}` would otherwise swallow `categories` and `tags`.
    Route::get('blog/posts/{slug}', [BlogPostController::class, 'show'])->name('blog.posts.show');
});

Route::post('/webhooks/evolution/{instance?}', [EvolutionWebhookController::class, 'handle'])
    ->name('api.webhooks.evolution');

// Meta Cloud API webhook: GET performs Meta's subscription handshake, POST receives
// events. Both are signature/token protected inside the controller.
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('api.webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('api.webhooks.meta');
