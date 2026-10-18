<?php

namespace App\Filter\Exceptions;

use RuntimeException;

/**
 * A notice with the same message is still pending or running (spec 006, FR-005).
 */
class NoticeAlreadyQueuedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('panel.notices.errors.duplicate'));
    }
}
