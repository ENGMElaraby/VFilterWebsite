<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PanelMail;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Filament sign-in plus a per email+IP throttle (FR-004) and audit of failed 2FA codes.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        // Only a valid, decrypted challenge counts as the second step.
        $isChallengeStep = $this->getUserUndertakingMultiFactorAuthentication() !== null;
        $email = mb_strtolower(trim((string) ($this->data['email'] ?? '')));
        $throttleKey = 'login:'.$email.'|'.request()->ip();

        if (! $isChallengeStep && RateLimiter::tooManyAttempts($throttleKey, (int) config('vfilter.login.max_attempts'))) {
            $this->refuseLockedOut($throttleKey, $email);

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            $this->recordFailure($isChallengeStep, $throttleKey);

            throw $exception;
        }

        if ($response !== null) {
            RateLimiter::clear($throttleKey);
        }

        return $response;
    }

    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent();

        // No "Forgot password?" link when the panel cannot send email.
        if ($component instanceof TextInput && ! PanelMail::isConfigured()) {
            $component->hint(null);
        }

        return $component;
    }

    private function refuseLockedOut(string $throttleKey, string $email): void
    {
        app(AuditLogger::class)->log('auth.lockout', actorType: 'guest', actorLabel: $email);

        $this->getRateLimitedNotification(new TooManyRequestsException(
            static::class,
            'authenticate',
            (string) request()->ip(),
            RateLimiter::availableIn($throttleKey),
        ))?->send();
    }

    private function recordFailure(bool $isChallengeStep, string $throttleKey): void
    {
        $challengedUser = $isChallengeStep ? $this->getUserUndertakingMultiFactorAuthentication() : null;

        if ($challengedUser instanceof User) {
            app(AuditLogger::class)->log('auth.two_factor_failed', $challengedUser, actorType: 'guest', actorLabel: $challengedUser->email);

            return;
        }

        RateLimiter::hit($throttleKey, (int) config('vfilter.login.decay_seconds'));
    }
}
