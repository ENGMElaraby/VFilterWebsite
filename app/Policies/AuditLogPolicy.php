<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Owners and admins read the audit log. Nobody writes to it from the panel.
 */
class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role->atLeast(UserRole::Admin);
    }

    public function view(User $actor, AuditLog $entry): bool
    {
        return $actor->role->atLeast(UserRole::Admin);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, AuditLog $entry): bool
    {
        return false;
    }

    public function delete(User $actor, AuditLog $entry): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
