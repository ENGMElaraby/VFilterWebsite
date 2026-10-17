<?php

use App\Models\AuditLog;
use App\Models\User;

it('shows guests the sign-in page in their browser language when it is arabic', function () {
    $this->get('/admin/login', ['Accept-Language' => 'ar-EG,ar;q=0.9,en;q=0.5'])
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('تسجيل الدخول');
});

it('falls back to english for other languages', function () {
    $this->get('/admin/login', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
        ->assertOk()
        ->assertSee('dir="ltr"', false)
        ->assertSee('Sign in');
});

it('applies the signed-in user language on every page', function () {
    $this->signIn(User::factory()->owner()->withTwoFactor()->create(['locale' => 'ar']));

    $this->get('/admin', ['Accept-Language' => 'en'])->assertOk()->assertSee('dir="rtl"', false);
    $this->get('/admin/users')->assertOk()->assertSee('المستخدمون');
});

it('saves a signed-in user switch on the account and audits it', function () {
    $user = $this->signIn();

    $this->from('/admin')->get('/admin/locale/ar')->assertRedirect('/admin');

    expect($user->fresh()->locale)->toBe('ar')
        ->and(AuditLog::where('action', 'user.updated')->sole()->new_values)->toBe(['locale' => 'ar']);
});

it('remembers a guest switch in the session', function () {
    $this->from('/admin/login')->get('/admin/locale/ar')->assertRedirect('/admin/login');

    $this->get('/admin/login', ['Accept-Language' => 'en'])->assertSee('dir="rtl"', false);
});

it('rejects unknown languages', function () {
    $this->get('/admin/locale/fr')->assertNotFound();
});
