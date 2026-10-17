<?php

namespace App\Logs;

use Carbon\CarbonImmutable;

final readonly class PruneResult
{
    public function __construct(public CarbonImmutable $cutoff, public int $deleted, public bool $complete) {}
}
