<?php

namespace App\Providers\Filament;

use App\Auth\MandatoryAppAuthentication;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RestrictToAllowedIps;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn (): string => __('panel.brand'))
            ->login(Login::class)
            ->multiFactorAuthentication(
                [MandatoryAppAuthentication::make()->recoverable()->recoveryCodeCount(8)],
                isRequired: true,
            )
            ->passwordReset(RequestPasswordReset::class)
            ->profile()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('panel.navigation.dashboard')),
                NavigationGroup::make()->label(fn (): string => __('panel.navigation.logs')),
                NavigationGroup::make()->label(fn (): string => __('panel.navigation.security')),
                NavigationGroup::make()->label(fn (): string => __('panel.navigation.settings')),
                NavigationGroup::make()->label(fn (): string => __('panel.navigation.in_game_rules')),
                NavigationGroup::make()->label(fn (): string => __('panel.navigation.administration')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                RestrictToAllowedIps::class,
                PreventBackHistory::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ]);
    }
}
