<?php

use App\Filament\Pages\ChangePassword;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('sends a user with a pending change to the change-password page', function () {
    $this->signIn(User::factory()->withTwoFactor()->mustChangePassword()->create());

    $this->get('/admin')->assertRedirect('/admin/change-password');
    $this->get('/admin/change-password')->assertOk();
});

it('clears the requirement after a valid change', function () {
    $user = $this->signIn(User::factory()->withTwoFactor()->mustChangePassword()->create());

    Livewire::test(ChangePassword::class)
        ->fillForm([
            'current_password' => 'password',
            'password' => 'my-own-strong-pass-3',
            'password_confirmation' => 'my-own-strong-pass-3',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('my-own-strong-pass-3', $user->password))->toBeTrue()
        ->and(AuditLog::where('action', 'user.password_changed')->sole()->actor_id)->toBe($user->id);

    $this->get('/admin')->assertOk();
});

it('rejects a wrong current password or a weak new one', function (array $data, string $field) {
    $this->signIn(User::factory()->withTwoFactor()->mustChangePassword()->create());

    Livewire::test(ChangePassword::class)
        ->fillForm(array_merge([
            'current_password' => 'password',
            'password' => 'my-own-strong-pass-3',
            'password_confirmation' => 'my-own-strong-pass-3',
        ], $data))
        ->call('save')
        ->assertHasFormErrors([$field]);
})->with([
    'wrong current' => [['current_password' => 'nope'], 'current_password'],
    'weak new' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
]);

it('does not show the page to users without a pending change', function () {
    $this->signIn();

    $this->get('/admin/change-password')->assertRedirect('/admin');
});
