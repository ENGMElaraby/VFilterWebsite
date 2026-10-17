<?php

namespace App\Filament\Resources\FilterLogs;

use App\Filament\Resources\FilterLogs\Pages\ListFilterLogs;
use App\Filament\Resources\FilterLogs\Pages\ViewFilterLog;
use App\Filament\Resources\FilterLogs\Schemas\FilterLogInfolist;
use App\Filament\Resources\FilterLogs\Tables\FilterLogsTable;
use App\Models\Filter\FilterLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Events written by the filter (spec 004). Read-only for every role (FilterLogPolicy).
 */
class FilterLogResource extends Resource
{
    protected static ?string $model = FilterLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.logs');
    }

    public static function getModelLabel(): string
    {
        return __('panel.logs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.logs.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return FilterLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FilterLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFilterLogs::route('/'),
            'view' => ViewFilterLog::route('/{record}'),
        ];
    }
}
