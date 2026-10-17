<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Filament's panel authentication, plus: a user disabled while signed in is signed out
 * and sent to the sign-in page instead of getting a 403 (FR-006).
 */
class Authenticate extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ! $user->is_active) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->unauthenticated($request, $guards);
        }

        parent::authenticate($request, $guards);
    }
}
