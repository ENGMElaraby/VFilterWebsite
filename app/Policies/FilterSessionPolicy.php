<?php

namespace App\Policies;

use App\Models\Filter\FilterSession;
use App\Models\User;

/**
 * Written by the filter: every signed-in role may read, nobody edits from the panel (spec 004).
 */
class FilterSessionPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, FilterSession $record): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, FilterSession $record): bool
    {
        return false;
    }

    public function delete(User $actor, FilterSession $record): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
