<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PanelLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the panel language: the signed-in user's choice, else the guest's session choice,
 * else the browser language (Arabic or English), else English (FR-020, FR-021).
 */
class ApplyUserPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User && in_array($user->locale, PanelLocale::SUPPORTED, true)) {
            return $user->locale;
        }

        $chosen = $request->hasSession() ? $request->session()->get('locale') : null;

        if (is_string($chosen) && in_array($chosen, PanelLocale::SUPPORTED, true)) {
            return $chosen;
        }

        return $request->getPreferredLanguage(PanelLocale::SUPPORTED) ?? PanelLocale::DEFAULT;
    }
}
