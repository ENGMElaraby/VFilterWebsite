<?php

namespace App\Filter\Exceptions;

use App\Enums\Filter\GatewayMode;
use RuntimeException;

/**
 * The gateway already reports the requested mode and no change is pending.
 */
class GatewayModeUnchangedException extends RuntimeException
{
    public function __construct(public readonly GatewayMode $mode)
    {
        parent::__construct(__('panel.commands.errors.mode_unchanged', [
            'mode' => __('panel.gateway_modes.'.$mode->value),
        ]));
    }
}
