<?php

namespace App\Filament\Pages\Security;

use App\Lists\ListName;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class BlockedHwids extends SecurityListPage
{
    protected static ?string $slug = 'security/blocked-hwids';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = 6;

    public static function usesRows(): bool
    {
        return true;
    }

    public static function list(): ListName
    {
        return ListName::BlockedHwids;
    }
}
