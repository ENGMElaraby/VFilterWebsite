<?php

namespace App\Config;

/**
 * Two modules would listen on the same guard address and port (spec 003, FR-017).
 */
class GuardAddressClashException extends InvalidModuleSetupException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(array $problems): self
    {
        return new self(implode(' ', $problems));
    }
}
