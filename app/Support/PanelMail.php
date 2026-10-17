<?php

namespace App\Support;

/**
 * Whether the panel can send email (password reset links). Off for the log/array mailers.
 */
class PanelMail
{
    public static function isConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
