<?php

namespace App\Enums\Filter;

use JsonException;

/**
 * Type of a filter setting. Values are stored as strings in filter_settings.value.
 */
enum SettingType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case String = 'string';
    case Json = 'json';
    case Color = 'color';

    public const MAX_STRING_LENGTH = 4000;

    private const COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/';

    /**
     * Stored string -> typed value.
     */
    public function cast(string $raw): mixed
    {
        return match ($this) {
            self::Bool => $raw === '1',
            self::Int => (int) $raw,
            self::Json => json_decode($raw, true),
            self::String, self::Color => $raw,
        };
    }

    /**
     * Typed value -> stored string. Call isValid() first.
     */
    public function serialize(mixed $value): string
    {
        return match ($this) {
            self::Bool => $value ? '1' : '0',
            self::Int => (string) (int) $value,
            self::Json => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            self::String, self::Color => (string) $value,
        };
    }

    public function isValid(mixed $value): bool
    {
        return match ($this) {
            self::Bool => is_bool($value),
            self::Int => is_int($value),
            self::String => is_string($value) && mb_strlen($value) <= self::MAX_STRING_LENGTH,
            self::Json => self::isJsonValue($value),
            self::Color => is_string($value) && preg_match(self::COLOR_PATTERN, $value) === 1,
        };
    }

    /**
     * Plain JSON data only: null, scalars and arrays that json_encode accepts.
     */
    private static function isJsonValue(mixed $value): bool
    {
        if (! is_array($value) && ! is_scalar($value) && $value !== null) {
            return false;
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }
}
