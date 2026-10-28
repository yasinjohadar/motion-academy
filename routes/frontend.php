<?php

use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\CourseController;
use App\Http\Controllers\Frontend\CourseLandingController;
use App\Http\Controllers\Frontend\EnrollmentController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\LessonController;
use App\Http\Controllers\Frontend\NewsletterController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\SeoController;
use App\Http\Controllers\Frontend\Student\DashboardController;
use App\Http\Controllers\Frontend\Student\LearnController;
use App\Http\Controllers\Frontend\Student\NotificationsController;
use App\Http\Controllers\Frontend\Student\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site (Blade)
|--------------------------------------------------------------------------
| Controllers: app/Http/Controllers/Frontend
| Views:       resources/views/frontend
| Assets:      public/frontend/assets
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

// Generated from live data; public/robots.txt must not exist or the web server serves it instead.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::get('/courses', [CourseController::class, 'index'])->name('courses');
Route::get('/search/suggest', SearchController::class)->name('search.suggest');
Route::get('/courses/search', [CourseController::class, 'search'])->name('courses.search');
Route::get('/courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
// Standalone advertising page for a course (no site header or footer).
Route::get('/l/{slug}', [CourseLandingController::class, 'show'])->name('courses.landing');

// Students: ask to join a course, withdraw the request, see "my courses".
Route::middleware('auth')->group(function () {
    Route::post('/courses/{slug}/enroll', [EnrollmentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('courses.enroll');
    Route::delete('/courses/{slug}/enroll', [EnrollmentController::class, 'destroy'])->name('courses.enroll.cancel');
    // Old address of "my courses", kept so existing links and emails still work.
    Route::permanentRedirect('/my-courses', '/dashboard/courses')->name('my-courses');
    // A guest's "log in to join" link: auth sends them to login, and the intended
    // redirect brings them back here, which returns them to the course page.
    Route::get('/courses/{slug}/join', fn (string $slug) => redirect()->to(route('courses.show', $slug).'#enroll'))
        ->name('courses.enroll.start');

    // The student dashboard.
    Route::prefix('dashboard')->name('student.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/courses', [DashboardController::class, 'courses'])->name('courses');
        Route::get('/requests', [DashboardController::class, 'requests'])->name('requests');
        Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [NotificationsController::class, 'read'])->name('notifications.read');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });

    // The lesson player and its progress calls.
    Route::prefix('learn')->name('learn.')->controller(LearnController::class)->group(function () {
        Route::post('/lessons/{lesson}/complete', 'complete')->whereNumber('lesson')->middleware('throttle:60,1')->name('complete');
        Route::post('/lessons/{lesson}/position', 'position')->whereNumber('lesson')->middleware('throttle:60,1')->name('position');
        Route::get('/{slug}', 'course')->name('course');
        Route::get('/{slug}/{lesson}', 'lesson')->whereNumber('lesson')->name('lesson');
    });
});
Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
Route::get('/lessons/{id}', [LessonController::class, 'show'])->whereNumber('id')->name('lessons.show');

Route::controller(PageController::class)->group(function () {
    Route::get('/about', 'about')->name('about');
    Route::get('/who-we-are', 'whoWeAre')->name('who-we-are');
    Route::get('/cart', 'cart')->name('cart');
    Route::get('/checkout', 'checkout')->name('checkout');
});

Route::post('/newsletter/subscribe', [NewsletterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

Route::prefix('newsletter/{token}')->middleware('throttle:20,1')->group(function () {
    Route::get('unsubscribe', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
    Route::post('unsubscribe', [NewsletterController::class, 'confirmUnsubscribe'])->name('newsletter.unsubscribe.confirm');
    Route::post('resubscribe', [NewsletterController::class, 'resubscribe'])->name('newsletter.resubscribe');
});

// Route names are kept from the previous (Inertia) blog so existing links keep working.
Route::prefix('blog')->name('blog.')->controller(BlogController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/search', 'search')->name('search');
    Route::get('/category/{slug}', 'category')->name('category');
    Route::get('/tag/{slug}', 'tag')->name('tag');
    Route::get('/{slug}', 'show')->name('show');
});
