<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a user on the change-password page until they replace an owner-set password (FR-013).
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (
            $user instanceof User
            && $user->must_change_password
            && ! $request->routeIs(ChangePassword::getRouteName(), 'filament.*.auth.logout')
        ) {
            return redirect()->to(ChangePassword::getUrl());
        }

        return $next($request);
    }
}
