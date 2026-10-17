<?php

namespace App\Policies;

use App\Models\Filter\FilterGaPermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Everyone sees Game Assistants; admins and the owner change them (spec 005).
 */
class FilterGaPermissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, FilterGaPermission $assistant): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-filter-settings');
    }

    public function update(User $actor, FilterGaPermission $assistant): bool
    {
        return Gate::forUser($actor)->allows('manage-filter-settings');
    }

    public function delete(User $actor, FilterGaPermission $assistant): bool
    {
        return Gate::forUser($actor)->allows('manage-filter-settings');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
