<?php

namespace App\Lists;

final readonly class SaveResult
{
    public function __construct(
        public int $added,
        public int $removed,
        public int $updated,
        public ParsedList $parsed,
    ) {}

    public function changed(): bool
    {
        return $this->added + $this->removed + $this->updated > 0;
    }
}
