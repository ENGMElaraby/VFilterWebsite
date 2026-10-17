<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterGaPermission;
use App\Models\Filter\FilterLog;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterSession;
use App\Models\User;
use App\Policies\FilterCommandPolicy;
use App\Policies\FilterGaPermissionPolicy;
use App\Policies\FilterLogPolicy;
use App\Policies\FilterModulePolicy;
use App\Policies\FilterSessionPolicy;
use App\Services\AuditLogger;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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

        // datetime2(3) on SQL Server instead of the older datetime (shared contract: datetime2, UTC).
        Schema::defaultTimePrecision(3);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(FilterCommand::class, FilterCommandPolicy::class);
        Gate::policy(FilterModule::class, FilterModulePolicy::class);
        Gate::policy(FilterLog::class, FilterLogPolicy::class);
        Gate::policy(FilterSession::class, FilterSessionPolicy::class);
        Gate::policy(FilterGaPermission::class, FilterGaPermissionPolicy::class);
        // Changing filter settings (004 retention, 006 catalog): admin and owner.
        Gate::define('manage-filter-settings', fn (User $user): bool => $user->role->atLeast(UserRole::Admin));

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
