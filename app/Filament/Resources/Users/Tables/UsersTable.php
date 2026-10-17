<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EndUserSessions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('panel.users.fields.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('panel.users.fields.email'))->searchable()->sortable(),
                TextColumn::make('role')
                    ->label(__('panel.users.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Owner => 'warning',
                        UserRole::Admin => 'info',
                        UserRole::Viewer => 'gray',
                    }),
                IconColumn::make('is_active')->label(__('panel.users.fields.active'))->boolean(),
                IconColumn::make('two_factor')
                    ->label(__('panel.users.fields.two_factor'))
                    ->state(fn (User $record): bool => $record->hasTwoFactorEnabled())
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label(__('panel.users.fields.last_login_at'))
                    ->dateTime(timezone: config('vfilter.display_timezone'))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->hidden(fn (User $record): bool => $record->isOwner()),
                ActionGroup::make([
                    self::resetPasswordAction(),
                    self::resetTwoFactorAction(),
                    self::disableAction(),
                    self::enableAction(),
                ]),
            ]);
    }

    private static function disableAction(): Action
    {
        return Action::make('disable')
            ->label(__('panel.users.actions.disable'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => ! $record->isOwner() && $record->is_active)
            ->action(function (User $record): void {
                $record->update(['is_active' => false]);
                app(EndUserSessions::class)($record);
            });
    }

    private static function enableAction(): Action
    {
        return Action::make('enable')
            ->label(__('panel.users.actions.enable'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (User $record): bool => ! $record->isOwner() && ! $record->is_active)
            ->action(fn (User $record) => $record->update(['is_active' => true]));
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label(__('panel.users.actions.reset_password'))
            ->icon(Heroicon::OutlinedKey)
            ->hidden(fn (User $record): bool => $record->isOwner())
            ->schema([
                TextInput::make('password')
                    ->label(__('panel.users.fields.new_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->confirmed()
                    ->rule(Password::defaults()),
                TextInput::make('password_confirmation')
                    ->label(__('panel.users.fields.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->action(function (User $record, array $data): void {
                $audit = app(AuditLogger::class);

                $audit->transaction(function () use ($audit, $record, $data): void {
                    $record->forceFill(['password' => $data['password'], 'must_change_password' => true])->saveQuietly();
                    $audit->log('user.password_reset', $record);
                });

                app(EndUserSessions::class)($record);

                Notification::make()->title(__('panel.users.notifications.password_reset'))->success()->send();
            });
    }

    private static function resetTwoFactorAction(): Action
    {
        return Action::make('resetTwoFactor')
            ->label(__('panel.users.actions.reset_two_factor'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('panel.users.help.reset_two_factor'))
            ->hidden(fn (User $record): bool => $record->isOwner())
            ->action(function (User $record): void {
                $audit = app(AuditLogger::class);

                $audit->transaction(function () use ($audit, $record): void {
                    $record->forceFill([
                        'app_authentication_secret' => null,
                        'app_authentication_recovery_codes' => null,
                    ])->saveQuietly();
                    $audit->log('user.two_factor_reset', $record);
                });

                app(EndUserSessions::class)($record);
            });
    }
}
