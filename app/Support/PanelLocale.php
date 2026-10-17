<?php

namespace App\Support;

/**
 * Panel languages. Arabic is shown right-to-left by Filament.
 */
class PanelLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public const DEFAULT = 'en';

    public static function other(string $locale): string
    {
        return $locale === 'ar' ? 'en' : 'ar';
    }

    public static function name(string $locale): string
    {
        return $locale === 'ar' ? 'العربية' : 'English';
    }
}
