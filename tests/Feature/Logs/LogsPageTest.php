<?php

use App\Filament\Resources\FilterLogs\FilterLogResource;
use App\Filament\Resources\FilterLogs\Pages\ListFilterLogs;
use App\Filament\Resources\FilterLogs\Pages\ViewFilterLog;
use App\Filament\Resources\FilterLogs\Tables\FilterLogsTable;
use App\Models\Filter\FilterLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:00:00', 'UTC'));
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
});

it('lists the last 24 hours newest first for every role', function () {
    $old = FilterLog::factory()->create(['ts' => now('UTC')->subDays(2), 'message' => 'old one']);
    $a = FilterLog::factory()->create(['ts' => now('UTC')->subHour(), 'message' => 'first']);
    $b = FilterLog::factory()->create(['ts' => now('UTC')->subMinute(), 'message' => 'second']);

    $this->get(FilterLogResource::getUrl())->assertOk();

    Livewire::test(ListFilterLogs::class)
        ->assertCanSeeTableRecords([$b, $a], inOrder: true)
        ->assertCanNotSeeTableRecords([$old]);
});

it('pages 50 events at a time', function () {
    FilterLog::factory()->count(55)->create(['ts' => now('UTC')->subMinute()]);

    expect(Livewire::test(ListFilterLogs::class)->instance()->getTableRecords())->toHaveCount(50);
});

it('narrows by the search filters', function () {
    $hit = FilterLog::factory()->create(['ip' => '10.9.9.9', 'username' => 'Sara', 'level' => 'security', 'ts' => now('UTC')->subMinute()]);
    $miss = FilterLog::factory()->create(['ip' => '10.9.9.8', 'username' => 'Sara', 'level' => 'security', 'ts' => now('UTC')->subMinute()]);

    Livewire::test(ListFilterLogs::class)
        ->filterTable(FilterLogsTable::SEARCH_FILTER, ['ip' => '10.9.9.9', 'username' => 'sara', 'levels' => ['security']])
        ->assertCanSeeTableRecords([$hit])
        ->assertCanNotSeeTableRecords([$miss]);
});

it('widens the time range on request', function () {
    $old = FilterLog::factory()->create(['ts' => now('UTC')->subDays(3)]);

    Livewire::test(ListFilterLogs::class)
        ->filterTable(FilterLogsTable::SEARCH_FILTER, ['from' => now('UTC')->subDays(7)->toDateTimeString()])
        ->assertCanSeeTableRecords([$old]);
});

it('shows one event in full, escaped', function () {
    $log = FilterLog::factory()->create(['message' => '<script>alert(1)</script>', 'data' => ['packet' => 'A1B2', 'note' => 'فارس']]);

    $this->get(FilterLogResource::getUrl('view', ['record' => $log]))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertSee('&quot;packet&quot;: &quot;A1B2&quot;', escape: false)
        ->assertSee('فارس');

    Livewire::test(ViewFilterLog::class, ['record' => $log->getKey()])->assertOk();
});

it('shows unknown levels as written instead of failing', function () {
    $log = FilterLog::factory()->create(['ts' => now('UTC')->subMinute()]);
    FilterLog::query()->whereKey($log->id)->toBase()->update(['level' => 'trace', 'category' => 'newthing']);

    Livewire::test(ListFilterLogs::class)->assertSee('trace')->assertSee('newthing');
});
