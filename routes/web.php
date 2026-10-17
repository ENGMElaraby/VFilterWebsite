<?php

use App\Http\Controllers\Panel\SaveThemeController;
use App\Http\Controllers\Panel\SwitchLocaleController;
use App\Http\Middleware\RestrictToAllowedIps;
use App\Support\PanelLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([RestrictToAllowedIps::class])->prefix('admin')->group(function (): void {
    Route::get('locale/{locale}', SwitchLocaleController::class)
        ->whereIn('locale', PanelLocale::SUPPORTED)
        ->name('panel.locale');

    Route::post('preferences/theme', SaveThemeController::class)
        ->middleware('auth')
        ->name('panel.preferences.theme');
});
