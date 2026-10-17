<?php

namespace App\Filament\Pages\Security;

use App\Lists\ListName;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ProxyBypassUsernames extends SecurityListPage
{
    protected static ?string $slug = 'security/proxy-bypass-usernames';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static ?int $navigationSort = 3;

    public static function usesRows(): bool
    {
        return true;
    }

    public static function list(): ListName
    {
        return ListName::ProxyBypassUsernames;
    }
}
