<?php

use App\Enums\Filter\ConfigArea;
use App\Filament\Resources\FilterLogs\Pages\ListFilterLogs;
use App\Logs\LogRetention;
use App\Models\AuditLog;
use App\Models\Filter\FilterConfigVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(fn () => $this->artisan('vfilter:install-defaults'));

it('lets admins change retention, audited and versioned', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $before = (int) FilterConfigVersion::query()->whereKey(ConfigArea::Settings->value)->value('version');

    Livewire::test(ListFilterLogs::class)
        ->callAction('retention', data: ['days' => 7])
        ->assertHasNoActionErrors()
        ->assertNotified(__('panel.retention.saved', ['days' => 7]));

    expect(app(LogRetention::class)->days())->toBe(7)
        ->and(AuditLog::query()->where('action', 'setting.updated')->count())->toBe(1)
        ->and((int) FilterConfigVersion::query()->whereKey(ConfigArea::Settings->value)->value('version'))->toBe($before + 1);
});

it('refuses values outside 1 to 3650 days', function (int $days) {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(ListFilterLogs::class)
        ->callAction('retention', data: ['days' => $days])
        ->assertHasActionErrors(['days']);

    expect(app(LogRetention::class)->days())->toBe(30);
})->with([0, 3651]);

it('warns before shortening retention', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(ListFilterLogs::class)
        ->mountAction('retention')
        ->assertMountedActionModalSee(__('panel.retention.help'));
    // The confirmation text appears once a smaller value is typed (live field).
    Livewire::test(ListFilterLogs::class)
        ->mountAction('retention')
        ->fillForm(['days' => 7])
        ->assertMountedActionModalSee(__('panel.retention.shorter_confirm', ['days' => 7]))
        ->fillForm(['days' => 60])
        ->assertMountedActionModalDontSee(__('panel.retention.shorter_confirm', ['days' => 60]));
});

it('keeps retention read-only for viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    Livewire::test(ListFilterLogs::class)->assertActionHidden('retention');
    expect(fn () => app(LogRetention::class)->set(7))->toThrow(AuthorizationException::class);
});

it('shows the last cleanup on the logs page', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());
    Livewire::test(ListFilterLogs::class)->assertSee(__('panel.logs.never_pruned'));

    $this->artisan('vfilter:prune-logs');

    Livewire::test(ListFilterLogs::class)->assertSee('0 events removed');
});
