<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\AppDatabaseChannel;
use App\Services\Ai\AiProviderConfigApplier;
use App\Services\Smtp\SmtpConfigApplier;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Persists the indexed category / level / action_url columns alongside the
        // framework's notification payload. ChannelManager::createDatabaseDriver()
        // resolves DatabaseChannel through the container, so this binding is enough.
        $this->app->bind(DatabaseChannel::class, AppDatabaseChannel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        SmtpConfigApplier::apply();
        AiProviderConfigApplier::apply();

        // The log viewer exposes everything in storage/logs, so it must be gated.
        // Opcodes\LogViewer checks this gate via its AuthorizeLogViewer middleware.
        Gate::define('viewLogViewer', fn (?User $user): bool => $user !== null
            && $user->can('admin.observability.logs'));
    }
}
