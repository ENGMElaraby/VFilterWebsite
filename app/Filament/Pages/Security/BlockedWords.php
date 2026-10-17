<?php

namespace App\Filament\Pages\Security;

use App\Lists\ListName;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class BlockedWords extends SecurityListPage
{
    protected static ?string $slug = 'security/blocked-words';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 8;

    public static function list(): ListName
    {
        return ListName::BlockedWords;
    }
}
