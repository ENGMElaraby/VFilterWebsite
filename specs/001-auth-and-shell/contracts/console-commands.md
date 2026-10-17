# Contract: Console commands

Run on the panel host: `php artisan <command>`. Passwords are always prompted (hidden input),
never accepted as arguments, and validated against the password policy.

## vfilter:create-owner

```
php artisan vfilter:create-owner [--email=] [--name=]
```
- Prompts for missing email/name, then password + confirmation.
- Fails (exit 1, message) if an owner already exists, the email is taken, or validation fails.
- Creates `role=owner`, `is_active=1`, `must_change_password=0`, no 2FA (enrolled at first sign-in).
- Audit: `user.created`, actor `console`.

## vfilter:reset-owner

```
php artisan vfilter:reset-owner [--password] [--two-factor]
```
- At least one option required; fails if no owner exists.
- `--password`: prompts a new password; rotates remember token.
- `--two-factor`: clears the owner's 2FA secret and recovery codes (re-enrol at next sign-in).
- Both: delete the owner's sessions.
- Audit: `user.password_reset` and/or `user.two_factor_reset`, actor `console`, target owner.
