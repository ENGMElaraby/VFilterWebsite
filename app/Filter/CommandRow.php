<?php

namespace App\Filter;

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Enums\Filter\GatewayMode;
use Carbon\CarbonImmutable;

/**
 * One line of the dashboard's recent commands list (spec 003, FR-012).
 */
final readonly class CommandRow
{
    public const RESULT_PREVIEW_LENGTH = 200;

    public function __construct(
        public int $id,
        public CommandName $command,
        public string $target,
        public ?GatewayMode $mode,
        public string $requestedBy,
        public CarbonImmutable $requestedAt,
        public CommandStatus $status,
        public ?CarbonImmutable $processedAt,
        public ?string $result,
        public ?string $shortResult,
        public bool $waitingOffline,
        public bool $notPickedUp,
    ) {}
}
