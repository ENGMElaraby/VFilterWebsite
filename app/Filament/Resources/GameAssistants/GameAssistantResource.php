<?php

namespace App\Filament\Resources\GameAssistants;

use App\Filament\Resources\GameAssistants\Pages\ListGameAssistants;
use App\Lists\GameAssistants;
use App\Models\Filter\FilterGaPermission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Game Assistants permission grid (spec 005 US2). Every toggle saves through GameAssistants.
 */
class GameAssistantResource extends Resource
{
    protected static ?string $model = FilterGaPermission::class;

    protected static ?string $slug = 'game-assistants';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.security');
    }

    public static function getModelLabel(): string
    {
        return __('panel.assistants.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.assistants.plural');
    }

    public static function table(Table $table): Table
    {
        $readOnly = fn (): bool => ! Gate::allows('manage-filter-settings');

        $toggles = array_map(
            fn (string $command): ToggleColumn => ToggleColumn::make($command)
                ->label(__('panel.assistants.commands.'.$command))
                ->disabled($readOnly)
                ->updateStateUsing(function (FilterGaPermission $record, bool $state) use ($command): bool {
                    app(GameAssistants::class)->set($record->username, [$command => $state]);

                    return $state;
                }),
            FilterGaPermission::COMMANDS,
        );

        return $table
            ->defaultSort('username')
            ->paginated(false)
            ->columns([
                TextColumn::make('username')->label(__('panel.assistants.username'))->searchable(),
                ...$toggles,
            ])
            ->recordActions([
                self::rowAction('allOn', true),
                self::rowAction('allOff', false),
                Action::make('remove')
                    ->label(__('panel.assistants.remove'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (FilterGaPermission $record): string => __('panel.assistants.remove_confirm', ['username' => $record->username]))
                    ->visible(fn (FilterGaPermission $record): bool => Gate::allows('delete', $record))
                    ->action(fn (FilterGaPermission $record) => app(GameAssistants::class)->remove($record->username)),
            ]);
    }

    private static function rowAction(string $name, bool $allowed): Action
    {
        return Action::make($name)
            ->label(__('panel.assistants.'.($allowed ? 'all_on' : 'all_off')))
            ->color('gray')
            ->visible(fn (FilterGaPermission $record): bool => Gate::allows('update', $record))
            ->action(function (FilterGaPermission $record) use ($allowed): void {
                app(GameAssistants::class)->setAll($record->username, $allowed);
                Notification::make()->success()->title(__('panel.assistants.'.($allowed ? 'all_on' : 'all_off')).': '.$record->username)->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameAssistants::route('/'),
        ];
    }
}
