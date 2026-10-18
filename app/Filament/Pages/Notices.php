<?php

namespace App\Filament\Pages;

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Filter\CommandQueue;
use App\Filter\Exceptions\CommandAlreadyStartedException;
use App\Filter\Exceptions\CommandInProgressException;
use App\Filter\Exceptions\NoticeAlreadyQueuedException;
use App\Models\Filter\FilterCommand;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Send a system notice to every in-world player and follow what the filter did with it (spec 006, US1).
 * Every role sees the list; admins and the owner send and cancel.
 *
 * @property-read Schema $form
 */
class Notices extends Page implements HasTable
{
    use InteractsWithTable;

    public const RECENT = 20;

    private const POLL = '5s';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.notices';

    /** @var array{message?: mixed} */
    public array $data = [];

    /** @var array<int, string>|null per request: panel user names by id */
    private ?array $names = null;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.dashboard');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.notices.title');
    }

    public function getTitle(): string
    {
        return __('panel.notices.title');
    }

    public function getSubheading(): string
    {
        return __('panel.notices.subheading');
    }

    public static function canSend(): bool
    {
        return Gate::allows('create', FilterCommand::class);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('message')
                    ->label(__('panel.notices.message'))
                    ->helperText(__('panel.notices.message_help', ['max' => CommandQueue::NOTICE_MAX_LENGTH]))
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $error = CommandQueue::noticeError(is_string($value) ? $value : '');

                        if ($error !== null) {
                            $fail($error);
                        }
                    })
                    ->extraInputAttributes(['dir' => 'auto']),
            ])
            ->statePath('data');
    }

    public function sendAction(): Action
    {
        return Action::make('send')
            ->label(__('panel.notices.send'))
            ->icon(Heroicon::OutlinedMegaphone)
            ->visible(fn (): bool => static::canSend())
            ->requiresConfirmation()
            ->modalDescription(__('panel.notices.send_confirm'))
            ->action(fn () => $this->send());
    }

    public function send(): void
    {
        $message = (string) ($this->form->getState()['message'] ?? '');

        try {
            app(CommandQueue::class)->notice($message);
        } catch (NoticeAlreadyQueuedException|CommandInProgressException|InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('panel.notices.queued'))->send();
        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        $zone = (string) config('vfilter.display_timezone');

        return $table
            ->heading(__('panel.notices.recent'))
            ->query(FilterCommand::query()->knownValues()->where('command', CommandName::Notice->value)->latest('id')->limit(self::RECENT))
            ->paginated(false)
            ->poll(self::POLL)
            ->columns([
                TextColumn::make('payload.message')
                    ->label(__('panel.notices.message'))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('created_by')
                    ->label(__('panel.commands.fields.requested_by'))
                    ->formatStateUsing(fn (FilterCommand $record): string => $this->requestedBy($record)),
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
                    }),
                TextColumn::make('processed_at')
                    ->label(__('panel.commands.fields.processed_at'))
                    ->dateTime('Y-m-d H:i:s', timezone: $zone)
                    ->placeholder('—'),
                TextColumn::make('result')
                    ->label(__('panel.commands.fields.result'))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('panel.commands.cancel'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('panel.commands.cancel_confirm'))
                    ->visible(fn (FilterCommand $record): bool => $record->status === CommandStatus::Pending && Gate::allows('cancel', $record))
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

    private function requestedBy(FilterCommand $record): string
    {
        $this->names ??= User::query()
            ->whereIn('id', $this->getTableRecords()->pluck('created_by')->unique())
            ->pluck('name', 'id')
            ->all();

        return $this->names[$record->created_by] ?? '#'.$record->created_by;
    }
}
