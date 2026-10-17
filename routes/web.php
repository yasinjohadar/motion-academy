<?php

use App\Http\Controllers\PrivateFileController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/frontend.php';
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
