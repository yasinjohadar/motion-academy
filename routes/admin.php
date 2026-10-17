<?php

use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\Admin\AppStorageController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BackupScheduleController;
use App\Http\Controllers\Admin\BlogAiController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\BlogTagController;
use App\Http\Controllers\Admin\CourseCategoryController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CourseEnrollmentController;
use App\Http\Controllers\Admin\CourseLessonController;
use App\Http\Controllers\Admin\CourseSectionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ObservabilityController;
use App\Http\Controllers\Admin\OtpSettingsController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SitePageController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SmtpSettingController;
use App\Http\Controllers\Admin\StorageDiskMappingController;
use App\Http\Controllers\Admin\StudentImpersonationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Modules\WhatsApp\Controllers\Admin\WhatsAppCampaignController;
use App\Modules\WhatsApp\Controllers\Admin\WhatsAppInstanceController;
use App\Modules\WhatsApp\Controllers\Admin\WhatsAppMessageController;
use App\Modules\WhatsApp\Controllers\Admin\WhatsAppSendController;
use App\Modules\WhatsApp\Controllers\Admin\WhatsAppSettingsController;
use App\Modules\WhatsApp\Controllers\Admin\WhatsAppWebhookEventController;
use Illuminate\Support\Facades\Route;

// Admins only: a student who opens /admin lands on their own dashboard instead.
Route::middleware(['auth', 'verified', 'admin.panel'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Own notifications — no permission gate, same as the profile routes above.
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/preferences', [NotificationController::class, 'preferences'])
            ->name('notifications.preferences');
        Route::put('notifications/preferences', [NotificationController::class, 'updatePreferences'])
            ->name('notifications.preferences.update');
        Route::post('notifications/push-subscription', [NotificationController::class, 'subscribePush'])
            ->name('notifications.push.subscribe');
        Route::delete('notifications/push-subscription', [NotificationController::class, 'unsubscribePush'])
            ->name('notifications.push.unsubscribe');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::delete('notifications/destroy-all', [NotificationController::class, 'destroyAll'])
            ->name('notifications.destroy-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])
            ->name('notifications.destroy');

        Route::prefix('observability')->name('observability.')->group(function () {
            Route::get('/', [ObservabilityController::class, 'index'])->name('index');
            Route::get('activity', [ObservabilityController::class, 'activity'])->name('activity');
            Route::get('security', [ObservabilityController::class, 'security'])->name('security');
            Route::get('sessions', [ObservabilityController::class, 'sessions'])->name('sessions');
            Route::delete('sessions/{session}', [ObservabilityController::class, 'revokeSession'])
                ->name('sessions.revoke');

            Route::get('webhooks', [WhatsAppWebhookEventController::class, 'index'])->name('webhooks');

            Route::get('database', [ObservabilityController::class, 'database'])->name('database');
            // Table names are validated against the real table list inside the
            // inspector, never interpolated into a query.
            Route::get('database/{table}', [ObservabilityController::class, 'databaseTable'])
                ->where('table', '[A-Za-z0-9_]+')->name('database.table');

            Route::get('failed-jobs', [ObservabilityController::class, 'failedJobs'])->name('failed-jobs');
            Route::post('failed-jobs/retry-all', [ObservabilityController::class, 'retryAllJobs'])
                ->name('failed-jobs.retry-all');
            Route::delete('failed-jobs/flush', [ObservabilityController::class, 'flushJobs'])
                ->name('failed-jobs.flush');
            Route::post('failed-jobs/{uuid}/retry', [ObservabilityController::class, 'retryJob'])
                ->name('failed-jobs.retry');
            Route::delete('failed-jobs/{uuid}', [ObservabilityController::class, 'forgetJob'])
                ->name('failed-jobs.forget');

            // Numeric constraint so this never shadows the named routes above.
            Route::get('{activity}', [ObservabilityController::class, 'show'])
                ->whereNumber('activity')->name('show');
        });

        Route::resource('users', UserController::class);
        // One-time link that opens a student's account in another browser.
        Route::post('users/{user}/impersonation-link', [StudentImpersonationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('users.impersonate');
        Route::put('users/{user}/password', [UserPasswordController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('users.password');
        Route::resource('roles', RoleController::class)->except(['show']);

        Route::resource('smtp-settings', SmtpSettingController::class)->except(['show']);
        Route::post('smtp-settings/test-draft', [SmtpSettingController::class, 'testDraft'])
            ->name('smtp-settings.test-draft');
        Route::post('smtp-settings/{smtp_setting}/test', [SmtpSettingController::class, 'test'])
            ->name('smtp-settings.test');
        Route::post('smtp-settings/{smtp_setting}/set-default', [SmtpSettingController::class, 'setDefault'])
            ->name('smtp-settings.set-default');
        Route::post('smtp-settings/{smtp_setting}/toggle-active', [SmtpSettingController::class, 'toggleActive'])
            ->name('smtp-settings.toggle-active');

        Route::resource('ai-providers', AiProviderController::class)->except(['show']);
        Route::post('ai-providers/test-draft', [AiProviderController::class, 'testDraft'])
            ->name('ai-providers.test-draft');
        Route::post('ai-providers/{ai_provider}/test', [AiProviderController::class, 'test'])
            ->name('ai-providers.test');
        Route::post('ai-providers/{ai_provider}/set-default', [AiProviderController::class, 'setDefault'])
            ->name('ai-providers.set-default');
        Route::post('ai-providers/{ai_provider}/toggle-active', [AiProviderController::class, 'toggleActive'])
            ->name('ai-providers.toggle-active');

        Route::get('whatsapp-settings', [WhatsAppSettingsController::class, 'index'])->name('whatsapp-settings.index');
        Route::put('whatsapp-settings', [WhatsAppSettingsController::class, 'update'])->name('whatsapp-settings.update');
        Route::post('whatsapp-settings/test', [WhatsAppSettingsController::class, 'test'])->name('whatsapp-settings.test');
        Route::get('whatsapp-settings/templates', [WhatsAppSettingsController::class, 'templates'])->name('whatsapp-settings.templates');

        Route::get('whatsapp-instances', [WhatsAppInstanceController::class, 'index'])->name('whatsapp-instances.index');
        Route::post('whatsapp-instances', [WhatsAppInstanceController::class, 'store'])->name('whatsapp-instances.store');
        Route::post('whatsapp-instances/sync', [WhatsAppInstanceController::class, 'sync'])->name('whatsapp-instances.sync');
        Route::get('whatsapp-instances/{instanceName}/connect', [WhatsAppInstanceController::class, 'connect'])->name('whatsapp-instances.connect');
        Route::post('whatsapp-instances/{instanceName}/toggle-rotation', [WhatsAppInstanceController::class, 'toggleRotation'])->name('whatsapp-instances.toggle-rotation');
        Route::post('whatsapp-instances/{instanceName}/set-default', [WhatsAppInstanceController::class, 'setDefault'])->name('whatsapp-instances.set-default');
        Route::post('whatsapp-instances/{instanceName}/qr', [WhatsAppInstanceController::class, 'fetchQr'])->name('whatsapp-instances.qr');
        Route::post('whatsapp-instances/{instanceName}/refresh', [WhatsAppInstanceController::class, 'refreshStatus'])->name('whatsapp-instances.refresh');
        Route::delete('whatsapp-instances/{instanceName}', [WhatsAppInstanceController::class, 'destroy'])->name('whatsapp-instances.destroy');

        Route::get('whatsapp-send', [WhatsAppSendController::class, 'create'])->name('whatsapp-send.create');
        Route::post('whatsapp-send', [WhatsAppSendController::class, 'store'])->name('whatsapp-send.store');

        Route::resource('whatsapp-campaigns', WhatsAppCampaignController::class)->only(['index', 'create', 'store', 'show']);

        Route::get('whatsapp-messages', [WhatsAppMessageController::class, 'index'])->name('whatsapp-messages.index');

        Route::get('site-settings', [SiteSettingController::class, 'edit'])->name('site-settings.edit');
        Route::put('site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');

        Route::resource('site-pages', SitePageController::class)->except(['show']);
        Route::post('site-pages/{site_page}/toggle-publish', [SitePageController::class, 'togglePublish'])
            ->name('site-pages.toggle-publish');

        Route::post('app-storage/test-draft', [AppStorageController::class, 'testDraft'])
            ->name('app-storage.test-draft');
        Route::post('app-storage/{app_storage}/test', [AppStorageController::class, 'test'])
            ->name('app-storage.test');
        Route::resource('app-storage', AppStorageController::class)->except(['show']);

        Route::resource('storage-disk-mappings', StorageDiskMappingController::class)->except(['show']);

        Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
        Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::get('media/{medium}', [MediaController::class, 'show'])->name('media.show');
        Route::put('media/{medium}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::get('backups/{backup}/status', [BackupController::class, 'status'])->name('backups.status');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::post('backups/{backup}/run', [BackupController::class, 'run'])->name('backups.run');
        Route::post('backups/{backup}/run-sync', [BackupController::class, 'runSync'])->name('backups.run-sync');
        Route::resource('backups', BackupController::class)->except(['edit', 'update']);

        Route::post('backup-schedules/{backup_schedule}/toggle-active', [BackupScheduleController::class, 'toggleActive'])
            ->name('backup-schedules.toggle-active');
        Route::post('backup-schedules/{backup_schedule}/execute', [BackupScheduleController::class, 'execute'])
            ->name('backup-schedules.execute');
        Route::resource('backup-schedules', BackupScheduleController::class)->except(['show']);

        Route::prefix('blog')->name('blog-')->group(function () {
            Route::resource('categories', BlogCategoryController::class)
                ->except(['show'])
                ->parameters(['categories' => 'blog_category']);
            Route::post('categories/{blog_category}/toggle-active', [BlogCategoryController::class, 'toggleActive'])
                ->name('categories.toggle-active');

            Route::resource('tags', BlogTagController::class)
                ->except(['show'])
                ->parameters(['tags' => 'blog_tag']);
            Route::post('tags/update-counts', [BlogTagController::class, 'updateCounts'])
                ->name('tags.update-counts');

            Route::resource('posts', BlogPostController::class)
                ->except(['show'])
                ->parameters(['posts' => 'blog_post']);
            Route::post('posts/{blog_post}/toggle-publish', [BlogPostController::class, 'togglePublish'])
                ->name('posts.toggle-publish');
            Route::post('posts/{blog_post}/toggle-featured', [BlogPostController::class, 'toggleFeatured'])
                ->name('posts.toggle-featured');
            Route::delete('posts/{blog_post}/image', [BlogPostController::class, 'deleteImage'])
                ->name('posts.delete-image');

            Route::get('ai', [BlogAiController::class, 'create'])->name('ai.create');
            Route::post('ai/generate', [BlogAiController::class, 'generate'])->name('ai.generate');
            Route::post('ai', [BlogAiController::class, 'store'])->name('ai.store');
        });

        Route::prefix('courses')->name('course-')->group(function () {
            Route::get('categories/trash', [CourseCategoryController::class, 'trash'])
                ->name('categories.trash');
            Route::post('categories/reorder', [CourseCategoryController::class, 'reorder'])
                ->name('categories.reorder');
            Route::post('categories/{course_category}/restore', [CourseCategoryController::class, 'restore'])
                ->withTrashed()
                ->name('categories.restore');
            Route::delete('categories/{course_category}/force', [CourseCategoryController::class, 'forceDelete'])
                ->withTrashed()
                ->name('categories.force-delete');
            Route::post('categories/{course_category}/toggle-active', [CourseCategoryController::class, 'toggleActive'])
                ->name('categories.toggle-active');
            Route::post('categories/{course_category}/toggle-featured', [CourseCategoryController::class, 'toggleFeatured'])
                ->name('categories.toggle-featured');
            Route::resource('categories', CourseCategoryController::class)
                ->except(['show'])
                ->parameters(['categories' => 'course_category']);
        });

        Route::get('settings/otp', [OtpSettingsController::class, 'edit'])->name('otp-settings.edit');
        Route::put('settings/otp', [OtpSettingsController::class, 'update'])->name('otp-settings.update');
        Route::post('settings/otp/test', [OtpSettingsController::class, 'testSend'])
            ->middleware('throttle:6,1')
            ->name('otp-settings.test');

        Route::prefix('enrollments')->name('enrollments.')->group(function () {
            Route::get('/', [CourseEnrollmentController::class, 'index'])->name('index');
            Route::get('students', [CourseEnrollmentController::class, 'students'])->name('students');
            Route::get('courses-for/{user}', [CourseEnrollmentController::class, 'coursesFor'])->name('courses-for');
            Route::post('/', [CourseEnrollmentController::class, 'store'])->name('store');
            Route::post('bulk-approve', [CourseEnrollmentController::class, 'bulkApprove'])->name('bulk-approve');
            Route::post('{enrollment}/approve', [CourseEnrollmentController::class, 'approve'])->name('approve');
            Route::post('{enrollment}/reject', [CourseEnrollmentController::class, 'reject'])->name('reject');
            Route::delete('{enrollment}', [CourseEnrollmentController::class, 'destroy'])->name('destroy');
        });

        Route::get('newsletter/export', [NewsletterSubscriberController::class, 'export'])->name('newsletter.export');
        Route::post('newsletter/{newsletter_subscriber}/toggle', [NewsletterSubscriberController::class, 'toggle'])->name('newsletter.toggle');
        Route::resource('newsletter', NewsletterSubscriberController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['newsletter' => 'newsletter_subscriber']);

        // Registered after the categories group so `courses/categories/*` is never read as `{course}`.
        Route::get('courses/trash', [CourseController::class, 'trash'])->name('courses.trash');
        Route::post('courses/{course}/restore', [CourseController::class, 'restore'])
            ->withTrashed()
            ->name('courses.restore');
        Route::delete('courses/{course}/force', [CourseController::class, 'forceDelete'])
            ->withTrashed()
            ->name('courses.force-delete');
        Route::post('courses/{course}/toggle-featured', [CourseController::class, 'toggleFeatured'])
            ->name('courses.toggle-featured');
        Route::post('courses/{course}/toggle-publish', [CourseController::class, 'togglePublish'])
            ->name('courses.toggle-publish');
        Route::post('courses/{course}/duplicate', [CourseController::class, 'duplicate'])
            ->name('courses.duplicate');
        Route::get('courses/{course}/curriculum', [CourseController::class, 'curriculum'])
            ->name('courses.curriculum');

        Route::scopeBindings()->prefix('courses/{course}')->name('courses.')->group(function () {
            Route::post('sections/reorder', [CourseSectionController::class, 'reorder'])->name('sections.reorder');
            Route::post('sections', [CourseSectionController::class, 'store'])->name('sections.store');
            Route::put('sections/{section}', [CourseSectionController::class, 'update'])->name('sections.update');
            Route::delete('sections/{section}', [CourseSectionController::class, 'destroy'])->name('sections.destroy');

            Route::post('sections/{section}/lessons/reorder', [CourseLessonController::class, 'reorder'])
                ->name('lessons.reorder');
            Route::post('sections/{section}/lessons', [CourseLessonController::class, 'store'])->name('lessons.store');
            Route::put('lessons/{lesson}', [CourseLessonController::class, 'update'])->name('lessons.update');
            Route::delete('lessons/{lesson}', [CourseLessonController::class, 'destroy'])->name('lessons.destroy');
        });

        Route::resource('courses', CourseController::class)->except(['show']);
    });
