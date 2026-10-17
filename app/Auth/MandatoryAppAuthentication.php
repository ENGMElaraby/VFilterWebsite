<?php

namespace App\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use SensitiveParameter;

/**
 * Filament's authenticator-app 2FA, made mandatory: users cannot turn it off (FR-003),
 * and enrolment, regenerated codes and used recovery codes are audited.
 */
class MandatoryAppAuthentication extends AppAuthentication
{
    /**
     * @return array<Action>
     */
    public function getActions(): array
    {
        return array_values(array_filter(
            parent::getActions(),
            fn (Action $action): bool => $action->getName() !== 'disableAppAuthentication',
        ));
    }

    public function saveSecret(HasAppAuthentication $user, #[SensitiveParameter] ?string $secret): void
    {
        parent::saveSecret($user, $secret);

        if ($secret !== null && $user instanceof User) {
            app(AuditLogger::class)->log('auth.two_factor_enabled', $user, actor: $user);
        }
    }

    /**
     * @param  array<string> | null  $codes
     */
    public function saveRecoveryCodes(HasAppAuthenticationRecovery $user, #[SensitiveParameter] ?array $codes): void
    {
        $isRegeneration = $codes !== null && filled($user->getAppAuthenticationRecoveryCodes());

        parent::saveRecoveryCodes($user, $codes);

        if ($isRegeneration && $user instanceof User) {
            app(AuditLogger::class)->log('auth.recovery_codes_regenerated', $user, actor: $user);
        }
    }

    public function verifyRecoveryCode(#[SensitiveParameter] string $recoveryCode, ?HasAppAuthenticationRecovery $user = null): bool
    {
        $isValid = parent::verifyRecoveryCode($recoveryCode, $user);

        if ($isValid && $user instanceof User) {
            app(AuditLogger::class)->log('auth.recovery_code_used', $user, actorType: 'guest', actorLabel: $user->email);
        }

        return $isValid;
    }
}
