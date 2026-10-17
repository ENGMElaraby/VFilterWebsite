<?php

namespace App\Lists;

use InvalidArgumentException;

/**
 * IPv4/IPv6 addresses and CIDR ranges in canonical form (spec 005, research R2).
 */
final class IpRange
{
    public const IPV4_MIN_PREFIX = 8;

    public const IPV6_MIN_PREFIX = 32;

    private const IPV4_BITS = 32;

    private const IPV6_BITS = 128;

    /**
     * Canonical text for an address or range; throws with a translation key as message.
     */
    public static function normalise(string $value, bool $allowRange): string
    {
        [$address, $prefix] = str_contains($value, '/') ? explode('/', $value, 2) : [$value, null];
        $packed = filter_var($address, FILTER_VALIDATE_IP) === false ? false : inet_pton($address);

        if ($packed === false) {
            throw new InvalidArgumentException('panel.lists.errors.ip');
        }

        if ($prefix === null) {
            return (string) inet_ntop($packed);
        }

        if (! $allowRange) {
            throw new InvalidArgumentException('panel.lists.errors.single_ip');
        }

        $bits = strlen($packed) === 4 ? self::IPV4_BITS : self::IPV6_BITS;
        $min = $bits === self::IPV4_BITS ? self::IPV4_MIN_PREFIX : self::IPV6_MIN_PREFIX;

        if (! ctype_digit($prefix) || (int) $prefix < $min || (int) $prefix > $bits) {
            throw new InvalidArgumentException('panel.lists.errors.prefix');
        }

        $prefix = (int) $prefix;

        // A full-length prefix is one address: store it bare so it dedupes with the plain form.
        return $prefix === $bits
            ? (string) inet_ntop($packed)
            : inet_ntop(self::mask($packed, $prefix)).'/'.$prefix;
    }

    /**
     * Whether $ip is the address or inside the range $entry (both already normalised).
     */
    public static function contains(string $entry, string $ip): bool
    {
        $packedIp = @inet_pton($ip);

        if ($packedIp === false) {
            return false;
        }

        if (! str_contains($entry, '/')) {
            return @inet_pton($entry) === $packedIp;
        }

        [$network, $prefix] = explode('/', $entry, 2);
        $packedNetwork = @inet_pton($network);

        return $packedNetwork !== false
            && strlen($packedNetwork) === strlen($packedIp)
            && self::mask($packedIp, (int) $prefix) === $packedNetwork;
    }

    private static function mask(string $packed, int $prefix): string
    {
        $masked = '';

        foreach (str_split($packed) as $index => $byte) {
            $keep = max(0, min(8, $prefix - $index * 8));
            $masked .= chr(ord($byte) & (0xFF << (8 - $keep)) & 0xFF);
        }

        return $masked;
    }
}
