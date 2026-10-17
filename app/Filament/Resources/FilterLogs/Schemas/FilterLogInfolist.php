<?php

namespace App\Filament\Resources\FilterLogs\Schemas;

use App\Models\Filter\FilterLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FilterLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $zone = (string) config('vfilter.display_timezone');
        $raw = fn (string $column) => fn (FilterLog $record): string => (string) $record->getRawOriginal($column);

        return $schema->components([
            Section::make()->columns(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                TextEntry::make('ts')->label(__('panel.logs.fields.ts'))->dateTime('Y-m-d H:i:s', timezone: $zone),
                TextEntry::make('level')->label(__('panel.logs.fields.level'))->state($raw('level'))->badge(),
                TextEntry::make('category')->label(__('panel.logs.fields.category'))->state($raw('category'))->badge(),
                TextEntry::make('module')->label(__('panel.logs.fields.module')),
                TextEntry::make('ip')->label(__('panel.logs.fields.ip'))->placeholder('—'),
                TextEntry::make('hwid')->label(__('panel.logs.fields.hwid'))->placeholder('—'),
                TextEntry::make('username')->label(__('panel.logs.fields.username'))->placeholder('—'),
                TextEntry::make('charname')->label(__('panel.logs.fields.charname'))->placeholder('—'),
                TextEntry::make('opcode')->label(__('panel.logs.fields.opcode'))->placeholder('—'),
                TextEntry::make('message')->label(__('panel.logs.fields.message'))->columnSpanFull(),
                TextEntry::make('data')->label(__('panel.logs.fields.data'))->columnSpanFull()->placeholder('—')
                    ->state(fn (FilterLog $record): ?string => $record->getRawOriginal('data') === null
                        ? null
                        : (string) json_encode(json_decode((string) $record->getRawOriginal('data')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->fontFamily('mono'),
            ]),
        ]);
    }
}
