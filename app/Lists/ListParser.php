<?php

namespace App\Lists;

use InvalidArgumentException;

/**
 * Reads "value // comment" lines into entries (spec 005, FR-002/FR-003). Pure: no database access.
 */
class ListParser
{
    public const MAX_ENTRIES = 10_000;

    public const COMMENT_MAX_LENGTH = 256;

    private const COMMENT_MARKER = '//';

    private const USERNAME_PATTERN = '/^[A-Za-z0-9_-]+$/';

    public function parse(ListName $list, string $text): ParsedList
    {
        $entries = [];
        $errors = [];
        $normalised = [];
        $duplicates = 0;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $index => $raw) {
            $line = $index + 1;
            [$value, $comment] = $this->split(trim($raw));

            if ($value === '') {
                continue;
            }

            try {
                $stored = $this->validate($list->format(), $value);
                $this->assertComment($comment);
            } catch (InvalidArgumentException $exception) {
                $errors[] = ['line' => $line, 'reason' => __($exception->getMessage(), ['max' => $list->format()->maxLength()])];

                continue;
            }

            if ($stored !== $value) {
                $normalised[] = ['line' => $line, 'from' => $value, 'to' => $stored];
            }

            $key = self::key($list, $stored);

            if (isset($entries[$key])) {
                $duplicates++;

                continue;
            }

            $entries[$key] = ['value' => $stored, 'comment' => $comment, 'line' => $line];
        }

        if (count($entries) > self::MAX_ENTRIES) {
            array_unshift($errors, ['line' => 0, 'reason' => __('panel.lists.errors.too_many', ['max' => number_format(self::MAX_ENTRIES)])]);
        }

        return new ParsedList($entries, $errors, $duplicates, $normalised);
    }

    /**
     * Comparison key: case-insensitive for names, HWIDs and words (contract 0.3.2).
     */
    public static function key(ListName $list, string $value): string
    {
        return $list->format()->isCaseInsensitive() ? mb_strtolower($value) : $value;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function split(string $line): array
    {
        $position = strpos($line, self::COMMENT_MARKER);

        if ($position === false) {
            return [$line, null];
        }

        $comment = trim(substr($line, $position + strlen(self::COMMENT_MARKER)));

        return [trim(substr($line, 0, $position)), $comment === '' ? null : $comment];
    }

    private function validate(ListFormat $format, string $value): string
    {
        if (mb_strlen($value) > $format->maxLength()) {
            throw new InvalidArgumentException('panel.lists.errors.too_long');
        }

        return match ($format) {
            ListFormat::IpOrRange => IpRange::normalise($value, allowRange: true),
            ListFormat::Ip => IpRange::normalise($value, allowRange: false),
            ListFormat::Username => preg_match(self::USERNAME_PATTERN, $value) === 1 ? $value : throw new InvalidArgumentException('panel.lists.errors.username'),
            ListFormat::Hwid => preg_match('/\s/u', $value) === 0 ? $value : throw new InvalidArgumentException('panel.lists.errors.hwid'),
            ListFormat::Word => $value,
        };
    }

    private function assertComment(?string $comment): void
    {
        if ($comment !== null && mb_strlen($comment) > self::COMMENT_MAX_LENGTH) {
            throw new InvalidArgumentException('panel.lists.errors.comment');
        }
    }
}
