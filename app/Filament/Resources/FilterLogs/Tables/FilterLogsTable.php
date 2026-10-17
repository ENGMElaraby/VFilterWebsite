<?php

namespace App\Filament\Resources\FilterLogs\Tables;

use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use App\Filter\DatabaseClock;
use App\Logs\LogFilters;
use App\Logs\LogQuery;
use App\Models\Filter\FilterLog;
use App\Models\Filter\FilterModule;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

class FilterLogsTable
{
    public const PER_PAGE = 50;

    public const MESSAGE_PREVIEW = 120;

    public const SEARCH_FILTER = 'search';

    private const LEVEL_COLORS = ['debug' => 'gray', 'info' => 'info', 'warn' => 'warning', 'error' => 'danger', 'security' => 'danger'];

    public static function configure(Table $table): Table
    {
        $zone = (string) config('vfilter.display_timezone');

        return $table
            ->paginationMode(PaginationMode::Simple)
            ->paginationPageOptions([self::PER_PAGE])
            ->defaultPaginationPageOption(self::PER_PAGE)
            // Same order as LogQuery, so the table never falls back to sorting by key first.
            ->defaultSort(fn (Builder $query): Builder => $query->reorder()->orderByDesc('ts')->orderByDesc('id'))
            ->columns([
                TextColumn::make('ts')->label(__('panel.logs.fields.ts'))->dateTime('Y-m-d H:i:s', timezone: $zone),
                self::rawBadge('level', 'panel.logs.levels', fn (string $state): string => self::LEVEL_COLORS[$state] ?? 'gray'),
                self::rawBadge('category', 'panel.logs.categories', fn (string $state): string => 'gray'),
                TextColumn::make('module')->label(__('panel.logs.fields.module')),
                TextColumn::make('ip')->label(__('panel.logs.fields.ip'))->placeholder('—'),
                TextColumn::make('hwid')->label(__('panel.logs.fields.hwid'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('username')->label(__('panel.logs.fields.username'))->placeholder('—'),
                TextColumn::make('charname')->label(__('panel.logs.fields.charname'))->placeholder('—'),
                TextColumn::make('opcode')->label(__('panel.logs.fields.opcode'))->placeholder('—'),
                TextColumn::make('message')->label(__('panel.logs.fields.message'))->limit(self::MESSAGE_PREVIEW)
                    ->tooltip(fn (FilterLog $record): string => $record->message)->wrap(),
            ])
            ->filters([self::searchFilter($zone)], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([ViewAction::make()]);
    }

    /**
     * The filter writes these columns; show the stored value even if it is not a known enum case.
     */
    private static function rawBadge(string $column, string $labels, Closure $color): TextColumn
    {
        return TextColumn::make($column)
            ->label(__('panel.logs.fields.'.$column))
            ->state(fn (FilterLog $record): string => (string) $record->getRawOriginal($column))
            ->formatStateUsing(fn (string $state): string => Lang::has($labels.'.'.$state) ? __($labels.'.'.$state) : $state)
            ->badge()
            ->color($color);
    }

    private static function searchFilter(string $zone): Filter
    {
        $labels = function (string $enum, string $prefix): array {
            $options = [];

            foreach ($enum::cases() as $case) {
                $options[$case->value] = __($prefix.'.'.$case->value);
            }

            return $options;
        };

        return Filter::make(self::SEARCH_FILTER)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                    DateTimePicker::make('from')->label(__('panel.logs.fields.from'))->seconds(false)->timezone($zone)
                        ->default(fn (): string => now('UTC')->subHours(LogFilters::DEFAULT_HOURS)->toDateTimeString()),
                    DateTimePicker::make('until')->label(__('panel.logs.fields.until'))->seconds(false)->timezone($zone),
                    Select::make('levels')->label(__('panel.logs.fields.level'))->multiple()->options($labels(LogLevel::class, 'panel.logs.levels')),
                    Select::make('categories')->label(__('panel.logs.fields.category'))->multiple()->options($labels(LogCategory::class, 'panel.logs.categories')),
                    Select::make('module')->label(__('panel.logs.fields.module'))
                        ->options(fn (): array => FilterModule::query()->orderBy('name')->pluck('name', 'name')->all()),
                    TextInput::make('ip')->label(__('panel.logs.fields.ip'))->maxLength(45),
                    TextInput::make('username')->label(__('panel.logs.fields.username'))->maxLength(32),
                    TextInput::make('charname')->label(__('panel.logs.fields.charname'))->maxLength(64),
                    TextInput::make('opcode')->label(__('panel.logs.fields.opcode'))->maxLength(8),
                ]),
            ])
            ->columnSpanFull()
            ->query(fn (Builder $query, array $data): Builder => app(LogQuery::class)->apply($query, self::filters($data)));
    }

    /**
     * Form data (UTC, as the picker stores it) to LogFilters. An inverted range falls back to the default.
     *
     * @param  array<string, mixed>  $data
     */
    public static function filters(array $data): LogFilters
    {
        $now = app(DatabaseClock::class)->now();

        try {
            return LogFilters::fromArray($data, $now, 'UTC');
        } catch (InvalidArgumentException) {
            return LogFilters::fromArray([], $now, 'UTC');
        }
    }
}
