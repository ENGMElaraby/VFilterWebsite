<?php

$csv = static fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [

    // Panel IP allow-list (IPs or CIDRs). Empty = off.
    'allowed_ips' => $csv(env('PANEL_ALLOWED_IPS')),

    // Time zone used to display times. Storage is always UTC.
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'UTC'),

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

    'audit' => [
        // Keys whose values are never written to audit_log.
        'redact' => [
            'password',
            'password_confirmation',
            'current_password',
            'remember_token',
            'app_authentication_secret',
            'app_authentication_recovery_codes',
            'token',
            'code',
        ],
    ],

];
