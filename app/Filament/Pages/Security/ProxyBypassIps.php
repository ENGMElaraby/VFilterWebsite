<?php

namespace App\Filament\Pages\Security;

use App\Lists\ListName;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ProxyBypassIps extends SecurityListPage
{
    protected static ?string $slug = 'security/proxy-bypass-ips';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 2;

    public static function usesRows(): bool
    {
        return true;
    }

    public static function list(): ListName
    {
        return ListName::ProxyBypassIps;
    }
}
