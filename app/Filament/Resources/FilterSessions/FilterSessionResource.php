<?php

namespace App\Filament\Resources\FilterSessions;

use App\Filament\Resources\FilterSessions\Pages\ListFilterSessions;
use App\Filament\Resources\FilterSessions\Tables\FilterSessionsTable;
use App\Models\Filter\FilterSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Connections the filter holds right now, with Kick and Ban for admins (spec 004, US5).
 */
class FilterSessionResource extends Resource
{
    protected static ?string $model = FilterSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.logs');
    }

    public static function getModelLabel(): string
    {
        return __('panel.sessions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.sessions.plural');
    }

    public static function table(Table $table): Table
    {
        return FilterSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFilterSessions::route('/'),
        ];
    }
}
