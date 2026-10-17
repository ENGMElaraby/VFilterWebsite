<?php

use App\Filament\Resources\FilterLogs\Pages\ListFilterLogs;
use App\Models\Filter\FilterLog;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->signIn(User::factory()->viewer()->withTwoFactor()->create()));

it('polls every 5 seconds only while Live is on', function () {
    $page = Livewire::test(ListFilterLogs::class)->assertDontSeeHtml('wire:poll.5s');

    $page->callAction('live')->assertSet('live', true)->assertSeeHtml('wire:poll.5s');
    $page->callAction('live')->assertSet('live', false)->assertDontSeeHtml('wire:poll.5s');
});

it('pauses when the user leaves the first page', function () {
    FilterLog::factory()->count(60)->create();

    Livewire::test(ListFilterLogs::class)
        ->callAction('live')
        ->call('gotoPage', 2, 'page')
        ->assertSet('live', false);
});

it('shows new events on refresh', function () {
    $page = Livewire::test(ListFilterLogs::class)->callAction('live');
    $log = FilterLog::factory()->create(['message' => 'brand new']);

    $page->call('$refresh')->assertCanSeeTableRecords([$log]);
});
