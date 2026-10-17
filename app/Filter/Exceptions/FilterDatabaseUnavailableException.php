<?php

namespace App\Filter\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The VFILTER database could not be reached; the dashboard shows a banner instead of failing (FR-006).
 */
class FilterDatabaseUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(__('panel.dashboard.database_unavailable'), 0, $previous);
    }
}
