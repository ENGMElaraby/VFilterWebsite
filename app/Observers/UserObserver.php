<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogger;
use BackedEnum;

/**
 * Audits changes to panel users. Creation is audited by whoever creates the user,
 * because only the caller knows the actor (console or owner).
 */
class UserObserver
{
    private const TRACKED = ['name', 'email', 'role', 'locale', 'theme'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged('is_active')) {
            $this->audit->log($user->is_active ? 'user.enabled' : 'user.disabled', $user);
        }

        $changed = array_values(array_intersect(self::TRACKED, array_keys($user->getChanges())));

        if ($changed === []) {
            return;
        }

        $old = [];
        $new = [];

        foreach ($changed as $field) {
            $old[$field] = $this->plain($user->getOriginal($field));
            $new[$field] = $this->plain($user->getAttribute($field));
        }

        $this->audit->log('user.updated', $user, $old, $new);
    }

    private function plain(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
