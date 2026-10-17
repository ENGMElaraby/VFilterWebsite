<?php

namespace App\Filter;

/**
 * Outcome of one connection check (spec 003, FR-022). Never carries credentials or raw errors.
 */
final readonly class HealthResult
{
    public const OK = 'ok';

    public const UNREACHABLE = 'unreachable';

    public const NOT_CONFIGURED = 'not_configured';

    /**
     * @param  list<string>  $missingTables
     */
    public function __construct(
        public string $state,
        public ?int $millis = null,
        public ?string $reason = null,
        public array $missingTables = [],
        public ?int $tableCount = null,
    ) {}

    public function presentTables(): ?int
    {
        return $this->tableCount === null ? null : $this->tableCount - count($this->missingTables);
    }

    /**
     * @return array{state: string, millis: int|null, reason: string|null, missingTables: list<string>, tableCount: int|null}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'millis' => $this->millis,
            'reason' => $this->reason,
            'missingTables' => $this->missingTables,
            'tableCount' => $this->tableCount,
        ];
    }
}
