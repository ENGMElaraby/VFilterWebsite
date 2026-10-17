<?php

namespace App\Providers;

use App\Services\AuditLogger;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class, fn () => new AuditLogger);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** @var list<string> $proxies */
        $proxies = config('vfilter.trusted_proxies', []);

        if ($proxies !== []) {
            TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        }

        Password::defaults(function (): Password {
            $rule = Password::min(12);

            // Breach check calls an external service, so only in production (FR-007).
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
