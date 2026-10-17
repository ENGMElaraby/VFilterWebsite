<?php

namespace Tests;

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * Current authenticator code for a user with two-factor enrolled.
     */
    protected function totpFor(User $user): string
    {
        return AppAuthentication::make()->getCurrentCode($user);
    }

    /**
     * Sign in as a fully set-up user (2FA enrolled, password not pending change).
     */
    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->withTwoFactor()->create();
        $this->actingAs($user);

        return $user;
    }
}
