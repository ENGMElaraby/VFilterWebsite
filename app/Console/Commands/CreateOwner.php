<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the one panel owner. The only way to create an owner (FR-008).
 */
class CreateOwner extends Command
{
    protected $signature = 'vfilter:create-owner {--email= : Owner email} {--name= : Owner name}';

    protected $description = 'Create the VFilter panel owner (only once)';

    public function handle(AuditLogger $audit): int
    {
        if (User::query()->where('role', UserRole::Owner->value)->exists()) {
            $this->error('An owner already exists. Use vfilter:reset-owner to recover access.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $owner = $audit->transaction(function () use ($audit, $email, $name, $password): User {
            $owner = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Owner,
                'is_active' => true,
                'must_change_password' => false,
            ]);

            $audit->asConsole()->log('user.created', $owner, new: [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => $owner->role->value,
            ]);

            return $owner;
        });

        $this->info("Owner {$owner->email} created. Two-factor setup is required at first sign-in.");

        return self::SUCCESS;
    }
}
