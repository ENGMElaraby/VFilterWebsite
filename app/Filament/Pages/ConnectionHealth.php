<?php

namespace App\Filament\Pages;

use App\Filter\ConnectionHealthCheck;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Read-only database connection check for every signed-in user (spec 003, US5).
 */
class ConnectionHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.connection-health';

    // Each run may wait up to 5 s per database; keep one user from tying up the server.
    private const RUNS_PER_MINUTE = 5;

    /** @var array<string, array<string, mixed>>|null */
    public ?array $results = null;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.dashboard');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.health.title');
    }

    public function getTitle(): string
    {
        return __('panel.health.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runCheck')
                ->label(__('panel.health.run'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $key = 'connection-health:'.auth()->id();

                    if (RateLimiter::tooManyAttempts($key, self::RUNS_PER_MINUTE)) {
                        Notification::make()->warning()->title(__('panel.health.too_many', ['seconds' => RateLimiter::availableIn($key)]))->send();

                        return;
                    }

                    RateLimiter::hit($key);
                    $this->results = array_map(
                        fn ($result): array => $result->toArray(),
                        app(ConnectionHealthCheck::class)->run(),
                    );
                }),
        ];
    }

    public function reasonLabel(?string $reason): string
    {
        if ($reason === null) {
            return '—';
        }

        return str_starts_with($reason, 'error:')
            ? __('panel.health.reasons.error', ['code' => substr($reason, strlen('error:'))])
            : __('panel.health.reasons.'.$reason);
    }
}
