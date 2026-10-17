<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label(__('panel.audit.fields.time'))
                    ->dateTime('Y-m-d H:i:s', timezone: config('vfilter.display_timezone')),
                TextEntry::make('action')
                    ->label(__('panel.audit.fields.action'))
                    ->formatStateUsing(fn (string $state): string => AuditLogResource::actionLabel($state)),
                TextEntry::make('actor_label')
                    ->label(__('panel.audit.fields.actor'))
                    ->placeholder('—')
                    ->helperText(fn (AuditLog $record): string => __('audit.actor_types.'.$record->actor_type)),
                TextEntry::make('ip')->label(__('panel.audit.fields.ip'))->placeholder('—'),
                TextEntry::make('target')
                    ->label(__('panel.audit.fields.target'))
                    ->state(fn (AuditLog $record): ?string => $record->target_type === null
                        ? null
                        : $record->target_type.' #'.$record->target_id)
                    ->placeholder('—'),
                TextEntry::make('changes')
                    ->label(__('panel.audit.fields.changes'))
                    ->state(fn (AuditLog $record): array => self::changes($record))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * One line per field: "field: old → new".
     *
     * @return list<string>
     */
    private static function changes(AuditLog $record): array
    {
        $old = $record->old_values ?? [];
        $new = $record->new_values ?? [];
        $lines = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $lines[] = sprintf('%s: %s → %s', $field, self::show($old[$field] ?? null), self::show($new[$field] ?? null));
        }

        return $lines;
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
