<?php

namespace App\Filter;

use App\Models\Filter\FilterCommand;

/**
 * Outcome of "All agents": the commands queued and the agents skipped because one was in progress.
 */
final readonly class AllAgentsResult
{
    /**
     * @param  list<FilterCommand>  $queued
     * @param  list<string>  $skipped  module names
     */
    public function __construct(public array $queued, public array $skipped) {}
}
