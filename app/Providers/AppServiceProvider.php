<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\AppDatabaseChannel;
use App\Services\Ai\AiProviderConfigApplier;
use App\Services\Smtp\SmtpConfigApplier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        /*
         * Turns three silent classes of bug into loud ones, outside production:
         *  - lazy loading a relation (the N+1 that only shows up under real traffic)
         *  - assigning an attribute that is not fillable (silently dropped writes)
         *  - reading an attribute that does not exist (typos returning null)
         *
         * Production keeps the permissive behaviour: a strictness exception there
         * would turn a slow page into a broken one.
         */
        Model::shouldBeStrict(! $this->app->isProduction());

        /*
         * Factories follow their model. Laravel's default guess derives the factory
         * namespace from the model's, which sends App\Modules\WhatsApp\Models\X to
         * Database\Factories\Modules\WhatsApp\Models\XFactory — a path that exists for
         * no one. A module keeps its factories inside itself so deleting the module
         * takes them with it; everything else stays in database/factories.
         */
        Factory::guessFactoryNamesUsing(function (string $model): string {
            if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\Models\\\\(.+)$/', $model, $matches)) {
                return "App\\Modules\\{$matches[1]}\\Database\\Factories\\{$matches[2]}Factory";
            }

            return 'Database\\Factories\\'.class_basename($model).'Factory';
        });

        /*
         * The `api` throttle the public routes reference. Laravel does not define it
         * for you, and a missing limiter is not an error — the middleware simply never
         * limits anything, which is the worst possible failure mode for a public
         * endpoint. Keyed by IP because the API is unauthenticated.
         */
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            (int) config('api.rate_limit', 60)
        )->by($request->ip()));

        Vite::prefetch(concurrency: 3);
        SmtpConfigApplier::apply();
        AiProviderConfigApplier::apply();

        // The log viewer exposes everything in storage/logs, so it must be gated.
        // Opcodes\LogViewer checks this gate via its AuthorizeLogViewer middleware.
        Gate::define('viewLogViewer', fn (?User $user): bool => $user !== null
            && $user->can('admin.observability.logs'));
    }
}
