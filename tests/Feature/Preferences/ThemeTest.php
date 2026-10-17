<?php

use App\Models\User;

it('saves the theme chosen in the panel on the account', function () {
    $user = $this->signIn();

    $this->postJson('/admin/preferences/theme', ['theme' => 'dark'])->assertNoContent();

    expect($user->fresh()->theme)->toBe('dark');
});

it('rejects unknown themes and guests', function () {
    $this->postJson('/admin/preferences/theme', ['theme' => 'dark'])->assertUnauthorized();

    $this->signIn();
    $this->postJson('/admin/preferences/theme', ['theme' => 'neon'])->assertUnprocessable();
});

it('applies the saved theme on every device before the page draws', function () {
    $this->signIn(User::factory()->withTwoFactor()->create(['theme' => 'dark']));

    $this->get('/admin')
        ->assertOk()
        ->assertSee("localStorage.setItem('filament:theme:mode', 'dark')", false)
        ->assertSee("addEventListener('theme-changed'", false);
});
