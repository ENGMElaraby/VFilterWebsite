<?php

use Illuminate\Support\Facades\Auth;

it('signs a disabled user out on the next request', function () {
    $user = $this->signIn();
    $this->get('/admin')->assertOk();

    $user->forceFill(['is_active' => false])->saveQuietly();

    $this->get('/admin')->assertRedirect('/admin/login');
    expect(Auth::check())->toBeFalse();
});
