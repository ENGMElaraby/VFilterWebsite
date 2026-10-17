<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PanelMail;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

/**
 * Email reset link: only when a mail server is configured (FR-007a). Requests are audited;
 * the visitor always sees the same neutral answer.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function mount(): void
    {
        abort_unless(PanelMail::isConfigured(), 404);

        parent::mount();
    }

    public function request(): void
    {
        $email = mb_strtolower(trim((string) ($this->data['email'] ?? '')));

        parent::request();

        app(AuditLogger::class)->log(
            'auth.password_reset_requested',
            User::query()->where('email', $email)->first(),
            actorType: 'guest',
            actorLabel: $email,
        );
    }
}
