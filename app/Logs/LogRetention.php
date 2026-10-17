<?php

namespace App\Logs;

use App\Settings\SettingsRepository;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * How many days of filter_logs to keep: `logs.retention_days` (spec 004, FR-007).
 */
class LogRetention
{
    public const KEY = 'logs.retention_days';

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 3650;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function days(): int
    {
        return (int) $this->settings->get(self::KEY);
    }

    public function set(int $days): void
    {
        Gate::authorize('manage-filter-settings');

        if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
            throw new InvalidArgumentException('Retention must be '.self::MIN_DAYS.'-'.self::MAX_DAYS.' days.');
        }

        $this->settings->set([self::KEY => $days]);
    }
}
