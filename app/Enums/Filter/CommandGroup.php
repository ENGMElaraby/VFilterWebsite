<?php

namespace App\Enums\Filter;

/**
 * Commands that may not run twice at once for the same target (spec 003, FR-011).
 */
enum CommandGroup: string
{
    case Power = 'power';
    case GatewayMode = 'gateway_mode';

    public static function for(CommandName $command): ?self
    {
        return match ($command) {
            CommandName::Start, CommandName::Stop, CommandName::Restart => self::Power,
            CommandName::SetGatewayMode => self::GatewayMode,
            default => null,
        };
    }

    /**
     * @return list<CommandName>
     */
    public function commands(): array
    {
        return match ($this) {
            self::Power => [CommandName::Start, CommandName::Stop, CommandName::Restart],
            self::GatewayMode => [CommandName::SetGatewayMode],
        };
    }
}
