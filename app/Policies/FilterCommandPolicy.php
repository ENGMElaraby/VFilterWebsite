<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Filter\FilterCommand;
use App\Models\User;

/**
 * Everyone sees commands; admins and the owner queue and cancel them (spec 003).
 */
class FilterCommandPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->role->atLeast(UserRole::Admin);
    }

    /**
     * Whether the command is still pending is decided by CommandQueue::cancel() at write time.
     */
    public function cancel(User $actor, FilterCommand $command): bool
    {
        return $actor->role->atLeast(UserRole::Admin);
    }
}
