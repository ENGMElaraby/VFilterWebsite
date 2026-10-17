<?php

namespace App\Logs;

use App\Models\Filter\FilterLog;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams filtered log events as CSV (spec 004, FR-006): UTF-8 BOM for spreadsheet programs,
 * formula-safe cells, newest first, capped, audited.
 */
class LogExporter
{
    public const MAX_ROWS = 100_000;

    public const COLUMNS = ['ts', 'level', 'category', 'module', 'ip', 'hwid', 'username', 'charname', 'opcode', 'message'];

    private const BOM = "\u{FEFF}";

    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(
        private readonly LogQuery $query,
        private readonly AuditLogger $audit,
        private readonly int $maxRows = self::MAX_ROWS,
    ) {}

    public function maxRows(): int
    {
        return $this->maxRows;
    }

    public function wouldTruncate(LogFilters $filters): bool
    {
        return $this->query->apply(FilterLog::query(), $filters)->toBase()->offset($this->maxRows)->limit(1)->value('id') !== null;
    }

    public function stream(LogFilters $filters, string $zone): StreamedResponse
    {
        $this->audit->log('logs.exported', null, [], [
            'filters' => $filters->toArray(),
            'truncated' => $this->wouldTruncate($filters),
        ]);

        $rows = $this->query->apply(FilterLog::query(), $filters)->toBase()->limit($this->maxRows);

        return response()->streamDownload(function () use ($rows, $zone): void {
            $out = fopen('php://output', 'w');
            fwrite($out, self::BOM);
            fputcsv($out, self::COLUMNS, escape: '');

            foreach ($rows->cursor() as $row) {
                fputcsv($out, $this->line($row, $zone), escape: '');
            }

            fclose($out);
        }, 'vfilter-logs-'.now($zone)->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    private function line(object $row, string $zone): array
    {
        $values = [];

        foreach (self::COLUMNS as $column) {
            $value = (string) ($row->{$column} ?? '');
            $values[] = $column === 'ts' ? CarbonImmutable::parse($value, 'UTC')->setTimezone($zone)->format('Y-m-d H:i:s') : $this->safe($value);
        }

        return $values;
    }

    private function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }
}
