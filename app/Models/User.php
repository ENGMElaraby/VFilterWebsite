<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A person who can sign in to the panel. Not a game account.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property bool $is_active
 * @property bool $must_change_password
 * @property string $locale
 * @property string $theme
 * @property Carbon|null $last_login_at
 */
#[ObservedBy(UserObserver::class)]
#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'must_change_password', 'locale', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role !== UserRole::Owner) {
                return;
            }

            $anotherOwnerExists = static::query()
                ->where('role', UserRole::Owner->value)
                ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
                ->exists();

            if ($anotherOwnerExists) {
                throw new LogicException('There can be only one owner.');
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
