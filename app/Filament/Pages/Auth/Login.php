<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Filament sign-in plus a per email+IP throttle (FR-004) and audit of failed 2FA codes.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $isChallengeStep = filled($this->userUndertakingMultiFactorAuthentication);
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
