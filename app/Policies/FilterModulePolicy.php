<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Filter\FilterModule;
use App\Models\User;

/**
 * Everyone sees modules (real addresses masked for viewers); admins and the owner change them.
 */
class FilterModulePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, FilterModule $module): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $this->canManage($actor);
    }

    public function update(User $actor, FilterModule $module): bool
    {
        return $this->canManage($actor);
    }

    public function delete(User $actor, FilterModule $module): bool
    {
        return $this->canManage($actor);
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function viewAddresses(User $actor): bool
    {
        return $this->canManage($actor);
    }

    private function canManage(User $actor): bool
    {
        return $actor->role->atLeast(UserRole::Admin);
    }
}
