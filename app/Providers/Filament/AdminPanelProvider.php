<?php

namespace App\Providers\Filament;

use App\Auth\MandatoryAppAuthentication;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\ApplyUserPreferences;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RestrictToAllowedIps;
use App\Support\PanelLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
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
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                Action::make('switchLocale')
                    ->label(fn (): string => PanelLocale::name(PanelLocale::other(app()->getLocale())))
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn (): string => route('panel.locale', PanelLocale::other(app()->getLocale()))),
            ])
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): View => view('filament.theme-sync'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): View => view('filament.login-language'))
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
            // Persistent so Livewire requests (form saves, notifications) use the same language.
            ->middleware([ApplyUserPreferences::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ]);
    }
}
