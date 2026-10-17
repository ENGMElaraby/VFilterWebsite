<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only the owner manages panel users, and nobody can change the owner row from the panel.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isOwner();
    }

    public function create(User $actor): bool
    {
        return $actor->isOwner();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isOwner() && ! $target->isOwner();
    }

    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
