<?php

namespace App\Lists;

use InvalidArgumentException;
use Normalizer;

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
            [$value, $comment] = $this->split(trim($raw), $list->format());

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
        if (! $list->format()->isCaseInsensitive()) {
            return $value;
        }

        // NFKC + full case folding, close to SQL Server's *_CI_AS collation (width, ß/ss), so
        // the (list, value) unique index never sees two values the parser kept apart.
        return mb_convert_case((string) Normalizer::normalize($value, Normalizer::FORM_KC), MB_CASE_FOLD);
    }

    /**
     * Words may contain "//" (e.g. "http://spam"), so there a comment must start the line or follow
     * a space. Other formats never contain "//".
     *
     * @return array{0: string, 1: string|null}
     */
    private function split(string $line, ListFormat $format): array
    {
        $pattern = $format === ListFormat::Word ? '~(?:^|\s)//(.*)$~u' : '~//(.*)$~u';

        if (preg_match($pattern, $line, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$line, null];
        }

        $comment = trim($match[1][0]);

        return [trim(substr($line, 0, $match[0][1])), $comment === '' ? null : $comment];
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
