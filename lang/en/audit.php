<?php

return [
    'actions' => [
        'auth.login' => 'Signed in',
        'auth.login_failed' => 'Failed sign-in',
        'auth.lockout' => 'Sign-in locked out',
        'auth.logout' => 'Signed out',
        'auth.password_reset_requested' => 'Password reset requested',
        'auth.password_reset' => 'Password reset by email',
        'auth.recovery_code_used' => 'Recovery code used',
        'auth.two_factor_failed' => 'Failed two-factor code',
        'auth.two_factor_enabled' => 'Two-factor enabled',
        'auth.recovery_codes_regenerated' => 'Recovery codes regenerated',
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.disabled' => 'User disabled',
        'user.enabled' => 'User enabled',
        'user.password_reset' => 'Password reset',
        'user.two_factor_reset' => 'Two-factor reset',
        'user.password_changed' => 'Password changed',
    ],

    'actor_types' => [
        'user' => 'User',
        'console' => 'Console',
        'system' => 'System',
        'guest' => 'Guest',
    ],
];
