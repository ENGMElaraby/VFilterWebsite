<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.audit.fields.time'))
                    ->dateTime('Y-m-d H:i:s', timezone: config('vfilter.display_timezone'))
                    ->sortable(),
                TextColumn::make('actor_label')
                    ->label(__('panel.audit.fields.actor'))
                    ->placeholder('—')
                    ->description(fn (AuditLog $record): string => __('audit.actor_types.'.$record->actor_type)),
                TextColumn::make('action')
                    ->label(__('panel.audit.fields.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AuditLogResource::actionLabel($state)),
                TextColumn::make('target')
                    ->label(__('panel.audit.fields.target'))
                    ->state(fn (AuditLog $record): ?string => $record->target_type === null
                        ? null
                        : $record->target_type.' #'.$record->target_id)
                    ->placeholder('—'),
                TextColumn::make('ip')->label(__('panel.audit.fields.ip'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('actor_id')
                    ->label(__('panel.audit.fields.actor'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('email', 'id')->all())
                    ->searchable(),
                SelectFilter::make('action')
                    ->label(__('panel.audit.fields.action'))
                    ->options(fn (): array => collect(array_keys((array) __('audit.actions')))
                        ->mapWithKeys(fn (string $action): array => [$action => AuditLogResource::actionLabel($action)])
                        ->all()),
                self::dateRangeFilter(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * Dates are picked in the display time zone and compared in UTC.
     */
    private static function dateRangeFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('from')->label(__('panel.audit.fields.from')),
                DatePicker::make('until')->label(__('panel.audit.fields.until')),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $zone = (string) config('vfilter.display_timezone');

                return $query
                    ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where(
                        'created_at', '>=', Carbon::parse($date, $zone)->startOfDay()->utc(),
                    ))
                    ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where(
                        'created_at', '<=', Carbon::parse($date, $zone)->endOfDay()->utc(),
                    ));
            });
    }
}
