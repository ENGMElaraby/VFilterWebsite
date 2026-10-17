<?php

namespace App\Lists;

use RuntimeException;

/**
 * The list was changed by someone else since it was loaded (spec 005, FR-005).
 */
class ListChangedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('panel.lists.changed_since'));
    }
}
