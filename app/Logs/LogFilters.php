<?php

namespace App\Logs;

use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Validated log search (spec 004, FR-002/FR-003). The time range is always set, so every query
 * is bounded on the indexed `ts` column. Times are UTC; inputs are read in the display zone.
 */
final readonly class LogFilters
{
    public const DEFAULT_HOURS = 24;

    /** Column lengths from the shared contract. */
    private const MAX_LENGTHS = ['module' => 32, 'ip' => 45, 'username' => 32, 'charname' => 64, 'opcode' => 8];

    /**
     * @param  list<LogLevel>  $levels
     * @param  list<LogCategory>  $categories
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        public array $levels = [],
        public array $categories = [],
        public ?string $module = null,
        public ?string $ip = null,
        public ?string $username = null,
        public ?string $charname = null,
        public ?string $opcode = null,
    ) {
        if ($from->greaterThan($until)) {
            throw new InvalidArgumentException(__('panel.logs.range_invalid'));
        }
    }

    /**
     * @param  array<string, mixed>  $input  from/until as display-zone date-times, the rest as typed
     */
    public static function fromArray(array $input, CarbonImmutable $now, string $zone): self
    {
        $text = [];

        foreach (self::MAX_LENGTHS as $field => $max) {
            $value = trim((string) ($input[$field] ?? ''));

            if (mb_strlen($value) > $max) {
                throw new InvalidArgumentException("{$field} is longer than {$max} characters.");
            }

            $text[$field] = $value === '' ? null : $value;
        }

        return new self(
            from: self::time($input['from'] ?? null, $zone) ?? $now->subHours(self::DEFAULT_HOURS),
            until: self::time($input['until'] ?? null, $zone) ?? $now,
            levels: self::enums(LogLevel::class, $input['levels'] ?? []),
            categories: self::enums(LogCategory::class, $input['categories'] ?? []),
            module: $text['module'],
            ip: $text['ip'],
            username: $text['username'],
            charname: $text['charname'],
            opcode: $text['opcode'] === null ? null : strtoupper($text['opcode']),
        );
    }

    /**
     * Plain form for the audit log.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from->toIso8601String(),
            'until' => $this->until->toIso8601String(),
            'levels' => array_map(fn (LogLevel $level): string => $level->value, $this->levels),
            'categories' => array_map(fn (LogCategory $category): string => $category->value, $this->categories),
            'module' => $this->module,
            'ip' => $this->ip,
            'username' => $this->username,
            'charname' => $this->charname,
            'opcode' => $this->opcode,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    private static function time(mixed $value, string $zone): ?CarbonImmutable
    {
        return blank($value) ? null : CarbonImmutable::parse((string) $value, $zone)->utc();
    }

    /**
     * @template T of LogLevel|LogCategory
     *
     * @param  class-string<T>  $enum
     * @return list<T>
     */
    private static function enums(string $enum, mixed $values): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value) => $enum::tryFrom((string) $value),
            is_array($values) ? $values : [],
        )));
    }
}
