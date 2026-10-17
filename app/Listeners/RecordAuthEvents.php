<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Writes sign-in, failed sign-in and sign-out to the audit log.
 * Registered by Laravel's listener discovery (handle* methods).
 */
class RecordAuthEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now('UTC')])->saveQuietly();
        $this->audit->log('auth.login', $event->user, actor: $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = mb_strtolower(trim((string) ($event->credentials['email'] ?? '')));

        $this->audit->log('auth.login_failed', actorType: 'guest', actorLabel: $email);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->log('auth.logout', $event->user, actor: $event->user);
    }
}
