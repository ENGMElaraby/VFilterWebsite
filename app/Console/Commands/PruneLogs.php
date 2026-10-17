<?php

namespace App\Console\Commands;

use App\Logs\LogPruner;
use Illuminate\Console\Command;

class PruneLogs extends Command
{
    protected $signature = 'vfilter:prune-logs';

    protected $description = 'Delete filter_logs older than logs.retention_days (runs daily)';

    public function handle(LogPruner $pruner): int
    {
        $result = $pruner->prune();

        $this->info(sprintf(
            'Removed %d events older than %s%s.',
            $result->deleted,
            $result->cutoff->toDateTimeString(),
            $result->complete ? '' : ' (time budget reached, more remain)',
        ));

        return self::SUCCESS;
    }
}
