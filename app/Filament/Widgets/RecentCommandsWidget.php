<?php

namespace App\Filament\Widgets;

use App\Enums\Filter\CommandStatus;
use App\Filter\CommandQueue;
use App\Filter\CommandRow;
use App\Filter\Exceptions\CommandAlreadyStartedException;
use App\Filter\Exceptions\FilterDatabaseUnavailableException;
use App\Filter\FilterOverview;
use App\Models\Filter\FilterCommand;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * The 20 newest commands with live status and Cancel while pending (spec 003, FR-012, FR-014a).
 */
class RecentCommandsWidget extends TableWidget
{
    public const REFRESH_EVENT = 'filter-command-queued';

    protected static bool $isDiscovered = false;

    // The dashboard's main content: render with the page instead of a second request (SC-001).
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /** @var array<int, CommandRow>|null per request, keyed by command id */
    private ?array $rows = null;

    public function table(Table $table): Table
    {
        $zone = (string) config('vfilter.display_timezone');

        return $table
            ->heading(__('panel.commands.recent'))
            ->query(FilterCommand::query()->latest('id')->limit(FilterOverview::RECENT_COMMANDS))
            ->paginated(false)
            ->poll('5s')
            ->columns([
                TextColumn::make('command')
                    ->label(__('panel.commands.fields.command'))
                    ->formatStateUsing(fn (FilterCommand $record): string => $this->commandLabel($record)),
                TextColumn::make('target')
                    ->label(__('panel.commands.fields.target'))
                    ->formatStateUsing(fn (string $state): string => $state === CommandQueue::ALL ? __('panel.commands.targets.all') : ucfirst($state)),
                TextColumn::make('created_by')
                    ->label(__('panel.commands.fields.requested_by'))
                    ->formatStateUsing(fn (FilterCommand $record): string => $this->row($record)->requestedBy ?? '#'.$record->created_by),
                TextColumn::make('created_at')
                    ->label(__('panel.commands.fields.requested_at'))
                    ->dateTime('Y-m-d H:i:s', timezone: $zone),
                TextColumn::make('status')
                    ->label(__('panel.commands.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (CommandStatus $state): string => __('panel.commands.statuses.'.$state->value))
                    ->color(fn (CommandStatus $state): string => match ($state) {
                        CommandStatus::Pending => 'warning',
                        CommandStatus::Running => 'info',
                        CommandStatus::Done => 'success',
                        CommandStatus::Failed => 'danger',
                        CommandStatus::Cancelled => 'gray',
                    })
                    ->description(fn (FilterCommand $record): ?string => $this->flag($record)),
                TextColumn::make('processed_at')
                    ->label(__('panel.commands.fields.processed_at'))
                    ->dateTime('Y-m-d H:i:s', timezone: $zone)
                    ->placeholder('—'),
                TextColumn::make('result')
                    ->label(__('panel.commands.fields.result'))
                    ->limit(CommandRow::RESULT_PREVIEW_LENGTH)
                    ->tooltip(fn (FilterCommand $record): ?string => $record->result)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('panel.commands.cancel'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('panel.commands.cancel_confirm'))
                    ->visible(fn (FilterCommand $record): bool => Gate::allows('cancel', $record))
                    ->action(function (FilterCommand $record): void {
                        try {
                            app(CommandQueue::class)->cancel($record);
                            Notification::make()->success()->title(__('panel.commands.cancelled'))->send();
                        } catch (CommandAlreadyStartedException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                        }
                    }),
            ]);
    }

    /**
     * Show a command queued from the module cards at once instead of on the next poll.
     */
    #[On(self::REFRESH_EVENT)]
    public function refreshCommands(): void
    {
        $this->rows = null;
    }

    private function commandLabel(FilterCommand $record): string
    {
        $label = __('panel.commands.names.'.$record->command->value);
        $mode = $this->row($record)?->mode;

        return $mode === null ? $label : $label.' → '.__('panel.gateway_modes.'.$mode->value);
    }

    private function flag(FilterCommand $record): ?string
    {
        $row = $this->row($record);

        return match (true) {
            $row === null => null,
            $row->notPickedUp => __('panel.commands.not_picked_up'),
            $row->waitingOffline => __('panel.commands.waiting_offline'),
            default => null,
        };
    }

    private function row(FilterCommand $record): ?CommandRow
    {
        if ($this->rows === null) {
            try {
                $this->rows = collect(app(FilterOverview::class)->recentCommands())->keyBy('id')->all();
            } catch (FilterDatabaseUnavailableException) {
                $this->rows = [];
            }
        }

        return $this->rows[$record->id] ?? null;
    }
}
