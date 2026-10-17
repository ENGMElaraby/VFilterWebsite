<?php

namespace App\Settings;

use InvalidArgumentException;

class UnknownSettingException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $keys
     */
    public static function forKeys(array $keys): self
    {
        return new self('Unknown filter setting(s): '.implode(', ', $keys));
    }
}
