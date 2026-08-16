<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\PrivateFileController;
use App\Support\PanelRedirector;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->to(PanelRedirector::url())
        : redirect()->route('login');
});

Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/search', [BlogController::class, 'search'])->name('search');
    Route::get('/category/{slug}', [BlogController::class, 'category'])->name('category');
    Route::get('/tag/{slug}', [BlogController::class, 'tag'])->name('tag');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('show');
});

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';

/*
| A private file behind a signed, expiring link. Only used by drivers that cannot
| sign their own object URLs; S3 hands the client a signed address directly.
*/
Route::get('files/{disk}/{path}', PrivateFileController::class)
    ->where('path', '.*')
    ->middleware('signed')
    ->name('files.private');
