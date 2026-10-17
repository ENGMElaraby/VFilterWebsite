<?php

namespace App\Filter;

use App\Enums\Filter\GatewayMode;
use App\Enums\Filter\ModuleState;
use App\Enums\Filter\ModuleType;
use Carbon\CarbonImmutable;

/**
 * One dashboard card: a configured module plus what the filter last reported about it.
 * Counts are null (unknown) while the module is offline (spec 003, FR-002/FR-003).
 */
final readonly class ModuleCard
{
    public function __construct(
        public string $name,
        public ModuleType $type,
        public string $target,
        public bool $enabled,
        public bool $online,
        public ?ModuleState $state,
        public ?int $connections,
        public ?int $onlineCount,
        public ?int $uptimeSeconds,
        public ?string $version,
        public ?string $lastError,
        public ?GatewayMode $gatewayMode,
        public ?string $unknownGatewayMode,
        public ?CarbonImmutable $lastHeartbeat,
        public ?GatewayMode $pendingMode,
    ) {}

    public function isGateway(): bool
    {
        return $this->type === ModuleType::Gateway;
    }

    public function isAgent(): bool
    {
        return $this->type === ModuleType::Agent;
    }
}
