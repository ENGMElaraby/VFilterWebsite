<?php

namespace App\Config;

use InvalidArgumentException;

class InvalidModuleSetupException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(array $problems): self
    {
        return new self(implode(' ', $problems));
    }
}
