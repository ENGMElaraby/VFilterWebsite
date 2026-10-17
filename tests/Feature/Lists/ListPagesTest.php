<?php

use App\Filament\Pages\Security\AllowedIps;
use App\Filament\Pages\Security\BlockedHwids;
use App\Filament\Pages\Security\BlockedIps;
use App\Filament\Pages\Security\BlockedWords;
use App\Filament\Pages\Security\NetCafeIps;
use App\Filament\Pages\Security\ProxyBypassIps;
use App\Filament\Pages\Security\ProxyBypassUsernames;
use App\Lists\CafeLimits;
use App\Lists\ListEditor;
use App\Lists\ListName;
use App\Models\Filter\FilterAreaRule;
use App\Models\Filter\FilterListEntry;
use App\Models\User;
use Livewire\Livewire;

const LIST_PAGES = [AllowedIps::class, ProxyBypassIps::class, ProxyBypassUsernames::class, BlockedIps::class, BlockedHwids::class, NetCafeIps::class, BlockedWords::class];

function seedList(ListName $list, string $text): void
{
    $editor = app(ListEditor::class);
    $editor->save($list, $text, $editor->fingerprint($list));
}

it('shows every list page to every role with its help and count', function (string $role) {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    seedList(ListName::BlockedIps, "1.2.3.4 // office\n5.6.7.8");
    $this->signIn(User::factory()->{$role}()->withTwoFactor()->create());

    foreach (LIST_PAGES as $page) {
        $this->get($page::getUrl())->assertOk()->assertSee($page::list()->label());
    }

    $rows = Livewire::test(BlockedIps::class)->assertSee(__('panel.lists.count', ['count' => 2]))->get('data.rows');
    expect(array_values($rows))->toBe([['value' => '1.2.3.4', 'comment' => 'office'], ['value' => '5.6.7.8', 'comment' => null]]);
})->with(['viewer', 'admin', 'owner']);

it('saves valid text and reports the counts', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    $page = Livewire::test(BlockedIps::class)
        ->set('data.rows', ['a' => ['value' => '1.2.3.4', 'comment' => null], 'b' => ['value' => '10.0.0.9/24', 'comment' => 'lan'], 'c' => ['value' => '1.2.3.4', 'comment' => null]])
        // No confirmation outside Allowed GM IPs: mounting saves at once.
        ->mountAction('save')
        ->assertActionNotMounted('save')
        ->assertHasNoErrors()
        ->assertNotified(__('panel.lists.saved', ['added' => 2, 'removed' => 0, 'updated' => 0]));

    expect(array_column(array_values($page->get('data.rows')), 'value'))->toBe(['1.2.3.4', '10.0.0.0/24']);
});

it('names invalid lines and saves nothing', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(BlockedWords::class)
        ->fillForm(['text' => "spam\n".str_repeat('x', 65)])
        ->callAction('save')
        ->assertHasErrors(['data.text'])
        ->assertSee(__('panel.lists.errors.line', ['line' => 2, 'reason' => __('panel.lists.errors.too_long', ['max' => 64])]));

    expect(FilterListEntry::count())->toBe(0);
});

it('is read-only for viewers', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    Livewire::test(BlockedWords::class)
        ->assertActionHidden('save')
        ->assertFormFieldIsDisabled('text');
});

it('tells the admin when someone else changed the list', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $page = Livewire::test(BlockedIps::class);
    seedList(ListName::BlockedIps, '9.9.9.9');

    $page->set('data.rows', ['a' => ['value' => '1.1.1.1', 'comment' => null]])
        ->callAction('save')
        ->assertNotified(__('panel.lists.changed_since'));

    expect(FilterListEntry::query()->pluck('value')->all())->toBe(['9.9.9.9']);
});

it('asks before removing the admin’s own IP from Allowed GM IPs', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    seedList(ListName::AllowedIps, "127.0.0.0/8\n8.8.8.8");

    Livewire::test(AllowedIps::class)
        ->set('data.rows', ['a' => ['value' => '8.8.8.8', 'comment' => null]])
        ->mountAction('save')
        ->assertMountedActionModalSee(__('panel.lists.own_ip_warning', ['ip' => '127.0.0.1']));

    Livewire::test(AllowedIps::class)
        ->set('data.rows', ['a' => ['value' => '127.0.0.1', 'comment' => 'me'], 'b' => ['value' => '8.8.8.8', 'comment' => null]])
        ->callAction('save')
        ->assertHasNoErrors();
    expect(FilterListEntry::query()->where('list', 'allowed_ips')->pluck('value')->sort()->values()->all())->toBe(['127.0.0.1', '8.8.8.8']);
});

it('sets special limits for a cafe IP from its page', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    seedList(ListName::NetCafeIps, '5.5.5.5');

    Livewire::test(NetCafeIps::class)
        ->callAction('cafeLimits', data: ['ip' => '5.5.5.5', ...array_fill_keys(CafeLimits::FIELDS, 0), 'login_ip' => 20, 'fw_ip' => -1])
        ->assertHasNoActionErrors()
        ->assertNotified(__('panel.cafe.saved', ['ip' => '5.5.5.5']));

    expect(FilterAreaRule::query()->sole()->params['login_ip'])->toBe(20);

    Livewire::test(NetCafeIps::class)
        ->callAction('cafeLimits', data: ['ip' => '5.5.5.5', ...array_fill_keys(CafeLimits::FIELDS, 0), 'login_ip' => 1001])
        ->assertHasActionErrors(['login_ip']);
});

it('edits short lists as rows with a value and comment', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    seedList(ListName::ProxyBypassUsernames, 'olduser // legacy');

    $page = Livewire::test(ProxyBypassUsernames::class);
    expect(array_values($page->get('data.rows')))->toBe([['value' => 'olduser', 'comment' => 'legacy']]);

    $page->set('data.rows', ['x' => ['value' => 'sara', 'comment' => 'GM helper'], 'y' => ['value' => 'ali', 'comment' => '']])
        ->callAction('save')
        ->assertHasNoErrors()
        ->assertNotified(__('panel.lists.saved', ['added' => 2, 'removed' => 1, 'updated' => 0]));

    expect(app(ListEditor::class)->text(ListName::ProxyBypassUsernames))->toBe("sara // GM helper\nali");
});

it('marks the invalid row', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(BlockedHwids::class)
        ->set('data.rows', ['ok' => ['value' => 'ABC123', 'comment' => null], 'bad' => ['value' => 'AB CD', 'comment' => null]])
        ->callAction('save')
        ->assertHasErrors(['data.rows.bad.value']);

    expect(FilterListEntry::count())->toBe(0);
});

it('uses rows for every list except blocked words', function () {
    expect(array_map(fn (string $page): bool => $page::usesRows(), LIST_PAGES))
        ->toBe([true, true, true, true, true, true, false]);
});

it('gives viewers rows they cannot change', function () {
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    Livewire::test(AllowedIps::class)->assertFormFieldIsDisabled('rows')->assertActionHidden('save');
});
