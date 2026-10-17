<?php

return [
    'brand' => 'VFilter',

    'navigation' => [
        'dashboard' => 'Dashboard',
        'logs' => 'Logs',
        'security' => 'Security',
        'settings' => 'Settings',
        'in_game_rules' => 'In-game rules',
        'administration' => 'Administration',
    ],

    'auth' => [
        'too_many_attempts' => 'Too many sign-in attempts. Try again in :seconds seconds.',
    ],

    'errors' => [
        'ip_not_allowed' => 'Access denied.',
    ],
    'users' => [
        'singular' => 'User',
        'plural' => 'Users',
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'active' => 'Active',
            'two_factor' => '2FA',
            'last_login_at' => 'Last sign-in',
            'initial_password' => 'Initial password',
            'new_password' => 'New password',
            'password_confirmation' => 'Confirm password',
        ],
        'help' => [
            'initial_password' => 'The user must change it at first sign-in.',
            'reset_two_factor' => 'The user will set up two-factor again at next sign-in. Their sessions end now.',
        ],
        'actions' => [
            'disable' => 'Disable',
            'enable' => 'Enable',
            'reset_password' => 'Reset password',
            'reset_two_factor' => 'Reset 2FA',
        ],
        'notifications' => [
            'password_reset' => 'Password reset. The user must change it at next sign-in.',
        ],
    ],

    'change_password' => [
        'title' => 'Change your password',
        'current' => 'Current password',
        'new' => 'New password',
        'confirm' => 'Confirm new password',
        'submit' => 'Change password',
        'done' => 'Password changed.',
    ],
];
