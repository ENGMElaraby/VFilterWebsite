<?php

namespace App\Lists;

/**
 * Named lists in filter_lists and their value formats (shared contract 0.3.2, spec 005).
 */
enum ListName: string
{
    case AllowedIps = 'allowed_ips';
    case ProxyBypassIps = 'proxy_bypass_ips';
    case ProxyBypassUsernames = 'proxy_bypass_usernames';
    case BlockedIps = 'blocked_ips';
    case BlockedHwids = 'blocked_hwids';
    case NetCafeIps = 'net_cafe_ips';
    case BlockedWords = 'blocked_words';

    public function format(): ListFormat
    {
        return match ($this) {
            self::AllowedIps, self::ProxyBypassIps, self::BlockedIps => ListFormat::IpOrRange,
            self::NetCafeIps => ListFormat::Ip,
            self::ProxyBypassUsernames => ListFormat::Username,
            self::BlockedHwids => ListFormat::Hwid,
            self::BlockedWords => ListFormat::Word,
        };
    }

    public function label(): string
    {
        return __('panel.lists.names.'.$this->value);
    }

    public function help(): string
    {
        return __('panel.lists.help.'.$this->value);
    }
}
