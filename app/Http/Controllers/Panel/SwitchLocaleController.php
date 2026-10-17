<?php

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Language switch: saved on the account when signed in, in the session for guests.
 */
class SwitchLocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->update(['locale' => $locale]);
        } else {
            $request->session()->put('locale', $locale);
        }

        return redirect()->back(fallback: '/admin');
    }
}
