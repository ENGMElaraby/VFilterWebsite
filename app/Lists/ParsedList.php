<?php

namespace App\Lists;

/**
 * Result of reading a list's text: entries keyed by their comparison key, and line errors.
 */
final readonly class ParsedList
{
    /**
     * @param  array<string, array{value: string, comment: string|null, line: int}>  $entries
     * @param  list<array{line: int, reason: string}>  $errors  line 0 = the whole list
     * @param  list<array{line: int, from: string, to: string}>  $normalised
     */
    public function __construct(
        public array $entries,
        public array $errors,
        public int $duplicates,
        public array $normalised,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
