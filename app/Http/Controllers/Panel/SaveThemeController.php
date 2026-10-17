<?php

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Saves the theme picked in Filament's theme switcher on the account (FR-021).
 */
class SaveThemeController
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'theme' => ['required', 'string', Rule::in(['light', 'dark', 'system'])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['theme' => $data['theme']]);

        return response()->noContent();
    }
}
