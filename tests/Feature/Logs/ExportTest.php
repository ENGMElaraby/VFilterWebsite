<?php

use App\Logs\LogExporter;
use App\Logs\LogFilters;
use App\Models\AuditLog;
use App\Models\Filter\FilterLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
});

function exportCsv(array $input = [], int $max = LogExporter::MAX_ROWS, string $zone = 'UTC'): string
{
    $exporter = app()->make(LogExporter::class, ['maxRows' => $max]);
    $response = $exporter->stream(LogFilters::fromArray($input, CarbonImmutable::now('UTC'), $zone), $zone);

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('writes a BOM, a header and the filtered rows newest first', function () {
    FilterLog::factory()->create(['ts' => '2026-10-17 10:00:00', 'message' => 'older', 'ip' => '10.0.0.1']);
    FilterLog::factory()->create(['ts' => '2026-10-17 11:00:00', 'message' => 'newer', 'ip' => '10.0.0.1']);
    FilterLog::factory()->create(['ts' => '2026-10-17 11:30:00', 'message' => 'other ip', 'ip' => '10.0.0.2']);

    $lines = explode("\n", trim(exportCsv(['ip' => '10.0.0.1'], zone: 'Asia/Riyadh')));

    expect($lines[0])->toStartWith("\u{FEFF}ts,level,category")
        ->and($lines)->toHaveCount(3)
        ->and($lines[1])->toStartWith('"2026-10-17 14:00:00"')->toContain('newer')
        ->and($lines[2])->toContain('older');
});

it('keeps Arabic text and neutralises formulas', function () {
    FilterLog::factory()->create(['message' => '=HYPERLINK("http://x")', 'username' => '@admin', 'charname' => 'فارس']);

    $csv = exportCsv();

    expect($csv)->toContain("\"'=HYPERLINK(\"\"http://x\"\")\"")
        ->and($csv)->toContain("'@admin")
        ->and($csv)->toContain('فارس');
});

it('stops at the row limit and says so in the audit entry', function () {
    FilterLog::factory()->count(5)->create();

    $lines = explode("\n", trim(exportCsv(max: 3)));

    expect($lines)->toHaveCount(4);
    $entry = AuditLog::query()->where('action', 'logs.exported')->sole();
    expect($entry->new_values['truncated'])->toBeTrue()
        ->and($entry->new_values['filters'])->toHaveKeys(['from', 'until']);
});
