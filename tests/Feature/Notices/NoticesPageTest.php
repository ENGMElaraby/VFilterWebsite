<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Filament\Pages\Notices;
use App\Filter\CommandQueue;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\User;
use Livewire\Livewire;

function notice(string $message, array $attributes = []): FilterCommand
{
    return FilterCommand::factory()->create(array_merge([
        'command' => CommandName::Notice,
        'target' => 'all',
        'payload' => ['message' => $message],
    ], $attributes));
}

it('lists only the newest notices, refreshing every 5 seconds', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $old = notice('oldest');
    $recent = collect(range(1, 20))->map(fn (int $i) => notice("notice {$i}"));
    $restart = FilterCommand::factory()->create();

    $this->get(Notices::getUrl())->assertOk();
    Livewire::test(Notices::class)
        ->assertCanSeeTableRecords($recent->reverse(), inOrder: true)
        ->assertCanNotSeeTableRecords([$old, $restart])
        ->assertSeeHtml('wire:poll.5s');
});

it('lets admins send a notice from the form', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(Notices::class)
        ->fillForm(['message' => '  Maintenance at 18:00  '])
        ->callAction('send')
        ->assertHasNoErrors()
        ->assertNotified(__('panel.notices.queued'))
        ->assertSet('data.message', null);

    expect(FilterCommand::query()->sole())
        ->command->toBe(CommandName::Notice)
        ->payload->toBe(['message' => 'Maintenance at 18:00']);
});

it('shows form errors and queues nothing for a bad message', function (string $message) {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(Notices::class)
        ->fillForm(['message' => $message])
        ->callAction('send')
        ->assertHasErrors(['data.message']);

    expect(FilterCommand::count())->toBe(0);
})->with(['   ', str_repeat('x', 201), "two\nlines"]);

it('tells the admin when the same notice is already queued', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    notice('Hello');

    Livewire::test(Notices::class)
        ->fillForm(['message' => 'Hello'])
        ->callAction('send')
        ->assertNotified(__('panel.notices.errors.duplicate'));

    expect(FilterCommand::count())->toBe(1);
});

it('is read-only for viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    $pending = notice('Hello');

    Livewire::test(Notices::class)
        ->assertActionHidden('send')
        ->assertDontSee(__('panel.notices.message_help', ['max' => CommandQueue::NOTICE_MAX_LENGTH]))
        ->assertTableActionHidden('cancel', $pending);
});

it('lets admins cancel a pending notice and audits it', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $pending = notice('Hello');
    $running = notice('Busy', ['status' => CommandStatus::Running]);

    Livewire::test(Notices::class)
        ->assertTableActionHidden('cancel', $running)
        ->callTableAction('cancel', $pending)
        ->assertNotified(__('panel.commands.cancelled'));

    expect($pending->fresh()->status)->toBe(CommandStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'command.cancelled')->count())->toBe(1);
});

it('hides Cancel once the filter claimed the notice', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $pending = notice('Hello');
    $page = Livewire::test(Notices::class)->assertTableActionVisible('cancel', $pending);
    FilterCommand::query()->whereKey($pending->id)->update(['status' => 'running']);

    $page->call('$refresh')->assertTableActionHidden('cancel', $pending->fresh());
});

it('shows the message and result escaped', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    notice('<b>bold</b>', ['status' => CommandStatus::Done, 'result' => 'notice sent to 3 player(s)']);

    Livewire::test(Notices::class)
        ->assertSeeHtml('&lt;b&gt;bold&lt;/b&gt;')
        ->assertSee('notice sent to 3 player(s)');
});
