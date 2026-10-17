<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('panel.users.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('panel.users.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (?User $record): Closure => self::uniqueEmailRule($record)),
                Select::make('role')
                    ->label(__('panel.users.fields.role'))
                    ->options(collect(UserRole::assignable())->mapWithKeys(
                        fn (UserRole $role): array => [$role->value => $role->label()],
                    ))
                    ->required(),
                TextInput::make('password')
                    ->label(__('panel.users.fields.initial_password'))
                    ->helperText(__('panel.users.help.initial_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->confirmed()
                    ->rule(Password::defaults())
                    ->visibleOn('create'),
                TextInput::make('password_confirmation')
                    ->label(__('panel.users.fields.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false)
                    ->visibleOn('create'),
            ]);
    }

    /**
     * Case-insensitive unique email (SQLite compares case-sensitively).
     */
    private static function uniqueEmailRule(?User $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $taken = User::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $value))])
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('validation.unique', ['attribute' => __('panel.users.fields.email')]));
            }
        };
    }
}
