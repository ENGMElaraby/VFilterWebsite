<?php

namespace App\Filament\Pages\Security;

use App\Lists\ListName;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class BlockedIps extends SecurityListPage
{
    protected static ?string $slug = 'security/blocked-ips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?int $navigationSort = 5;

    public static function usesRows(): bool
    {
        return true;
    }

    public static function list(): ListName
    {
        return ListName::BlockedIps;
    }
}
