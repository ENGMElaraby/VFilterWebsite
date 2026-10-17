<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = $this->signIn(User::factory()->owner()->withTwoFactor()->create());
    $this->staff = User::factory()->admin()->withTwoFactor()->create();
    DB::table('sessions')->insert([
        'id' => 'staff-session', 'user_id' => $this->staff->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);
});

function staffSessions(User $user): int
{
    return DB::table('sessions')->where('user_id', $user->id)->count();
}

it('disables and re-enables a user', function () {
    Livewire::test(ListUsers::class)->callTableAction('disable', $this->staff);

    expect($this->staff->fresh()->is_active)->toBeFalse()
        ->and(staffSessions($this->staff))->toBe(0)
        ->and(AuditLog::where('action', 'user.disabled')->sole()->target_id)->toBe((string) $this->staff->id);

    Livewire::test(ListUsers::class)->callTableAction('enable', $this->staff);

    expect($this->staff->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::where('action', 'user.enabled')->count())->toBe(1);
});

it('resets a password and forces a change at next sign-in', function () {
    Livewire::test(ListUsers::class)
        ->callTableAction('resetPassword', $this->staff, [
            'password' => 'another-strong-pass-2',
            'password_confirmation' => 'another-strong-pass-2',
        ])
        ->assertHasNoTableActionErrors();

    $staff = $this->staff->fresh();

    expect(Hash::check('another-strong-pass-2', $staff->password))->toBeTrue()
        ->and($staff->must_change_password)->toBeTrue()
        ->and(staffSessions($staff))->toBe(0)
        ->and(AuditLog::where('action', 'user.password_reset')->sole()->new_values)->toBeNull()
        ->and(json_encode(AuditLog::all()->toArray()))->not->toContain('another-strong-pass-2');
});

it('resets two-factor so the user enrols again', function () {
    Livewire::test(ListUsers::class)->callTableAction('resetTwoFactor', $this->staff);

    $staff = $this->staff->fresh();

    expect($staff->app_authentication_secret)->toBeNull()
        ->and($staff->app_authentication_recovery_codes)->toBeNull()
        ->and(staffSessions($staff))->toBe(0)
        ->and(AuditLog::where('action', 'user.two_factor_reset')->count())->toBe(1);
});

it('offers no row actions on the owner and no delete at all', function () {
    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('disable', $this->owner)
        ->assertTableActionHidden('resetPassword', $this->owner)
        ->assertTableActionHidden('resetTwoFactor', $this->owner)
        ->assertTableActionHidden('edit', $this->owner)
        ->assertTableActionDoesNotExist('delete');
});
