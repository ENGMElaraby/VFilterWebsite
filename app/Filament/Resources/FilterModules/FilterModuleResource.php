<?php

namespace App\Filament\Resources\FilterModules;

use App\Filament\Resources\FilterModules\Pages\EditFilterModule;
use App\Filament\Resources\FilterModules\Pages\ListFilterModules;
use App\Filament\Resources\FilterModules\Schemas\FilterModuleForm;
use App\Filament\Resources\FilterModules\Tables\FilterModulesTable;
use App\Models\Filter\FilterModule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Gateway, Download and Agent1-5 addresses (spec 003, US4). Everyone sees the list; admins and
 * the owner edit (FilterModulePolicy). Saves go through ConfigStore.
 */
class FilterModuleResource extends Resource
{
    protected static ?string $model = FilterModule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.dashboard');
    }

    public static function getModelLabel(): string
    {
        return __('panel.modules.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.modules.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return FilterModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FilterModulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFilterModules::route('/'),
            'edit' => EditFilterModule::route('/{record}/edit'),
        ];
    }
}
