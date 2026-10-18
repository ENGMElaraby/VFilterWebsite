<?php

namespace App\Filament\Resources\FilterSessions\Tables;

use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandInProgressException;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\Filter\FilterSession;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class FilterSessionsTable
{
    private const POLL = '5s';

    private const PERMANENT = 'permanent';

    private const DEFAULT_BAN_MINUTES = 1440;

    public static function configure(Table $table): Table
    {
        $zone = (string) config('vfilter.display_timezone');

        return $table
            ->heading(fn (): string => __('panel.sessions.heading', ['count' => rescue(fn (): int => FilterSession::query()->count(), 0, report: false)]))
            ->poll(self::POLL)
            ->defaultSort('connected_at', 'desc')
            ->columns([
                TextColumn::make('module')->label(__('panel.sessions.fields.module')),
                TextColumn::make('ip')->label(__('panel.sessions.fields.ip')),
                TextColumn::make('hwid')->label(__('panel.sessions.fields.hwid'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('username')->label(__('panel.sessions.fields.username'))->placeholder('—'),
                TextColumn::make('charname')->label(__('panel.sessions.fields.charname'))->placeholder('—'),
                TextColumn::make('connected_at')->label(__('panel.sessions.fields.connected_at'))->dateTime('Y-m-d H:i:s', timezone: $zone)->sortable(),
                TextColumn::make('last_packet_at')->label(__('panel.sessions.fields.last_packet_at'))->since()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('module')->label(__('panel.sessions.fields.module'))
                    ->options(fn (): array => FilterModule::query()->orderBy('name')->pluck('name', 'name')->all()),
                self::exact('ip'),
                self::exact('username'),
                self::exact('charname'),
            ])
            ->recordActions([self::kickAction(), self::banAction()]);
    }

    private static function exact(string $column): Filter
    {
        return Filter::make($column)
            ->schema([TextInput::make('value')->label(__('panel.sessions.fields.'.$column))])
            ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null) ? $query : $query->where($column, trim((string) $data['value'])));
    }

    private static function kickAction(): Action
    {
        return Action::make('kick')
            ->label(__('panel.sessions.kick'))
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn (FilterSession $record): string => __('panel.sessions.kick_confirm', ['target' => self::target($record)]))
            ->visible(fn (): bool => Gate::allows('create', FilterCommand::class))
            ->action(fn (FilterSession $record) => self::attempt(function () use ($record): string {
                $command = app(CommandQueue::class)->kick($record);

                return __('panel.sessions.kick_queued', ['target' => $command->target]);
            }));
    }

    private static function banAction(): Action
    {
        // Only the lengths the filter side can apply (vfilter.timed_bans); Permanent is always offered.
        $timed = app(CommandQueue::class)->banDurations();
        $durations = [];

        foreach ($timed as $minutes) {
            $durations[(string) $minutes] = __('panel.sessions.durations.'.$minutes);
        }

        $durations[self::PERMANENT] = __('panel.sessions.durations.permanent');
        $default = in_array(self::DEFAULT_BAN_MINUTES, $timed, true) ? (string) self::DEFAULT_BAN_MINUTES : self::PERMANENT;

        return Action::make('ban')
            ->label(__('panel.sessions.ban'))
            ->color('danger')
            ->modalHeading(fn (FilterSession $record): string => __('panel.sessions.ban_heading', ['username' => $record->username]))
            ->visible(fn (FilterSession $record): bool => filled($record->username) && Gate::allows('create', FilterCommand::class))
            ->schema([
                Select::make('duration')->label(__('panel.sessions.duration'))->options($durations)->default($default)->required(),
                Textarea::make('reason')->label(__('panel.sessions.reason'))->required()->maxLength(CommandQueue::REASON_MAX_LENGTH),
            ])
            ->action(fn (FilterSession $record, array $data) => self::attempt(function () use ($record, $data): string {
                $minutes = $data['duration'] === self::PERMANENT ? null : (int) $data['duration'];
                $command = app(CommandQueue::class)->ban($record, (string) $data['reason'], $minutes);

                return __('panel.sessions.ban_queued', ['target' => $command->target]);
            }));
    }

    private static function target(FilterSession $session): string
    {
        return $session->charname ?: ($session->username ?: $session->ip);
    }

    /**
     * @param  Closure(): string  $command
     */
    private static function attempt(Closure $command): void
    {
        try {
            Notification::make()->success()->title($command())->send();
        } catch (CommandInProgressException|InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }
}
