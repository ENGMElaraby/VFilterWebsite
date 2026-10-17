<?php

namespace App\Filament\Pages\Security;

use App\Lists\ListName;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class AllowedIps extends SecurityListPage
{
    protected static ?string $slug = 'security/allowed-ips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 1;

    public static function usesRows(): bool
    {
        return true;
    }

    public static function list(): ListName
    {
        return ListName::AllowedIps;
    }
}
