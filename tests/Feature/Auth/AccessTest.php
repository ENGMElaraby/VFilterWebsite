<?php

use App\Models\User;

it('sends guests to the sign-in page', function (string $path) {
    $this->get($path)->assertRedirect('/admin/login');
})->with(['/admin', '/admin/multi-factor-authentication/set-up']);

it('returns to the requested page after sign-in', function () {
    $this->get('/admin');

    expect(session('url.intended'))->toBe(url('/admin'));
});

it('keeps panel pages out of the browser cache', function (string $path) {
    $user = User::factory()->withTwoFactor()->create();

    $response = $path === '/admin/login' ? $this->get($path) : $this->actingAs($user)->get($path);

    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')->toContain('private')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');
})->with(['/admin/login', '/admin']);

it('ends idle sessions after two hours', function () {
    expect(config('session.lifetime'))->toBe(120);
});
