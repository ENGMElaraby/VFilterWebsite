<?php

namespace App\Filament\Widgets;

use App\Filter\Exceptions\FilterDatabaseUnavailableException;
use App\Filter\FilterOverview;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Players in game: sum over online agents (spec 003, FR-004).
 */
class OnlinePlayersWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '5s';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        try {
            $players = app(FilterOverview::class)->onlinePlayers();
        } catch (FilterDatabaseUnavailableException) {
            $players = null;
        }

        return [
            Stat::make(__('panel.dashboard.online_players'), $players === null ? __('panel.module_card.unknown') : (string) $players)
                ->description(__('panel.dashboard.online_players_help'))
                ->icon(Heroicon::OutlinedUserGroup),
        ];
    }
}
