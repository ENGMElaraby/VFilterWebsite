<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ModuleStatusWidget;
use App\Filament\Widgets\OnlinePlayersWidget;
use App\Filament\Widgets\RecentCommandsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Filter control home page (spec 003): module status, online players, commands.
 */
class Dashboard extends BaseDashboard
{
    public static function getNavigationLabel(): string
    {
        return __('panel.dashboard.title');
    }

    public function getTitle(): string
    {
        return __('panel.dashboard.title');
    }

    public function getColumns(): int
    {
        return 1;
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [OnlinePlayersWidget::class, ModuleStatusWidget::class, RecentCommandsWidget::class];
    }
}
