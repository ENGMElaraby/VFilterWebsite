<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Resources\AuditLogs\Schemas\AuditLogInfolist;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only audit log (owner + admin, AuditLogPolicy).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.administration');
    }

    public static function getModelLabel(): string
    {
        return __('panel.audit.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.audit.plural');
    }

    /**
     * Label for an action id; unknown ids (e.g. from newer features) are shown as-is.
     */
    public static function actionLabel(string $action): string
    {
        // Action ids contain dots, so look them up in the array, not as a dotted key.
        $labels = (array) __('audit.actions');

        return is_string($labels[$action] ?? null) ? $labels[$action] : $action;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
