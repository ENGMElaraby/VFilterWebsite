<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\EndUserSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Recovers owner access from the server: new password and/or cleared two-factor (FR-008).
 */
class ResetOwner extends Command
{
    protected $signature = 'vfilter:reset-owner
        {--password : Set a new owner password}
        {--two-factor : Clear the owner two-factor so it is set up again at next sign-in}';

    protected $description = 'Reset the VFilter panel owner password and/or two-factor';

    public function handle(AuditLogger $audit, EndUserSessions $endSessions): int
    {
        $resetPassword = (bool) $this->option('password');
        $resetTwoFactor = (bool) $this->option('two-factor');

        if (! $resetPassword && ! $resetTwoFactor) {
            $this->error('Pass --password and/or --two-factor.');

            return self::FAILURE;
        }

        $owner = User::query()->where('role', UserRole::Owner->value)->first();

        if ($owner === null) {
            $this->error('No owner exists. Use vfilter:create-owner.');

            return self::FAILURE;
        }

        $newPassword = $resetPassword ? $this->askNewPassword() : null;

        if ($resetPassword && $newPassword === null) {
            return self::FAILURE;
        }

        $console = $audit->asConsole();

        $audit->transaction(function () use ($owner, $newPassword, $resetTwoFactor, $console): void {
            if ($newPassword !== null) {
                $owner->forceFill(['password' => $newPassword])->saveQuietly();
                $console->log('user.password_reset', $owner);
            }

            if ($resetTwoFactor) {
                $owner->forceFill([
                    'app_authentication_secret' => null,
                    'app_authentication_recovery_codes' => null,
                ])->saveQuietly();
                $console->log('user.two_factor_reset', $owner);
            }
        });

        $endSessions($owner);

        $this->info('Owner access reset. All owner sessions were ended.');

        return self::SUCCESS;
    }

    private function askNewPassword(): ?string
    {
        $password = (string) $this->secret('New password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return $password;
    }
}
