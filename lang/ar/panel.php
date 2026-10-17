<?php

return [
    'brand' => 'VFilter',

    'navigation' => [
        'dashboard' => 'لوحة التحكم',
        'logs' => 'السجلات',
        'security' => 'الأمان',
        'settings' => 'الإعدادات',
        'in_game_rules' => 'قواعد اللعبة',
        'administration' => 'الإدارة',
    ],

    'auth' => [
        'too_many_attempts' => 'محاولات تسجيل دخول كثيرة. حاول مرة أخرى بعد :seconds ثانية.',
    ],

    'errors' => [
        'ip_not_allowed' => 'الوصول مرفوض.',
    ],
    'users' => [
        'singular' => 'مستخدم',
        'plural' => 'المستخدمون',
        'fields' => [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'role' => 'الدور',
            'active' => 'نشط',
            'two_factor' => 'التحقق الثنائي',
            'last_login_at' => 'آخر تسجيل دخول',
            'initial_password' => 'كلمة المرور الأولية',
            'new_password' => 'كلمة المرور الجديدة',
            'password_confirmation' => 'تأكيد كلمة المرور',
        ],
        'help' => [
            'initial_password' => 'يجب على المستخدم تغييرها عند أول تسجيل دخول.',
            'reset_two_factor' => 'سيعيد المستخدم إعداد التحقق الثنائي عند تسجيل الدخول التالي. تنتهي جلساته الآن.',
        ],
        'actions' => [
            'disable' => 'تعطيل',
            'enable' => 'تفعيل',
            'reset_password' => 'إعادة تعيين كلمة المرور',
            'reset_two_factor' => 'إعادة تعيين التحقق الثنائي',
        ],
        'notifications' => [
            'password_reset' => 'تمت إعادة تعيين كلمة المرور. يجب على المستخدم تغييرها عند تسجيل الدخول التالي.',
        ],
    ],

    'change_password' => [
        'title' => 'غيّر كلمة المرور',
        'current' => 'كلمة المرور الحالية',
        'new' => 'كلمة المرور الجديدة',
        'confirm' => 'تأكيد كلمة المرور الجديدة',
        'submit' => 'تغيير كلمة المرور',
        'done' => 'تم تغيير كلمة المرور.',
    ],
    'audit' => [
        'singular' => 'سجل تدقيق',
        'plural' => 'سجل التدقيق',
        'fields' => [
            'time' => 'الوقت',
            'actor' => 'المنفّذ',
            'action' => 'الإجراء',
            'target' => 'العنصر',
            'ip' => 'عنوان IP',
            'changes' => 'التغييرات (القديم ← الجديد)',
            'from' => 'من',
            'until' => 'إلى',
        ],
    ],
    'profile' => [
        'locale' => 'اللغة',
        'theme' => 'المظهر',
        'themes' => [
            'light' => 'فاتح',
            'dark' => 'داكن',
            'system' => 'حسب الجهاز',
        ],
    ],
];
