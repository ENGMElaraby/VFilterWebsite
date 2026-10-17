<?php

namespace App\Logs;

use App\Filter\DatabaseClock;
use App\Models\AuditLog;
use App\Models\Filter\FilterLog;
use App\Services\AuditLogger;

/**
 * Deletes filter_logs rows older than the retention period in short batches, within a time
 * budget, and records each run in the audit log (spec 004, FR-008/FR-009, research R4).
 */
class LogPruner
{
    public const BATCH_SIZE = 5000;

    public const BUDGET_SECONDS = 120;

    public const AUDIT_ACTION = 'logs.pruned';

    private const NANOSECONDS_PER_SECOND = 1_000_000_000;

    public function __construct(
        private readonly LogRetention $retention,
        private readonly DatabaseClock $clock,
        private readonly AuditLogger $audit,
        private readonly int $batchSize = self::BATCH_SIZE,
    ) {}

    public function prune(int $budgetSeconds = self::BUDGET_SECONDS): PruneResult
    {
        $cutoff = $this->clock->now()->subDays($this->retention->days());
        $deadline = hrtime(true) + $budgetSeconds * self::NANOSECONDS_PER_SECOND;
        $deleted = 0;
        $complete = false;

        do {
            $batch = FilterLog::query()->where('ts', '<', $cutoff)->orderBy('id')->limit($this->batchSize)->delete();
            $deleted += $batch;

            if ($batch < $this->batchSize) {
                $complete = true;

                break;
            }
        } while (hrtime(true) < $deadline);

        $this->audit->asSystem()->log(self::AUDIT_ACTION, null, [], [
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
            'complete' => $complete,
        ]);

        return new PruneResult($cutoff, $deleted, $complete);
    }

    public static function lastRun(): ?AuditLog
    {
        return AuditLog::query()->where('action', self::AUDIT_ACTION)->latest('id')->first();
    }
}
