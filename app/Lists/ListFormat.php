<?php

namespace App\Lists;

/**
 * What one line of a list may hold.
 */
enum ListFormat: string
{
    case IpOrRange = 'ip_or_range';
    case Ip = 'ip';
    case Username = 'username';
    case Hwid = 'hwid';
    case Word = 'word';

    public function maxLength(): int
    {
        return match ($this) {
            self::IpOrRange, self::Ip => 45,
            self::Username => 32,
            self::Hwid, self::Word => 64,
        };
    }

    public function isCaseInsensitive(): bool
    {
        return in_array($this, [self::Username, self::Hwid, self::Word], true);
    }
}
