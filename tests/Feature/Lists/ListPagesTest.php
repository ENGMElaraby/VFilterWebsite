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

    Livewire::test(BlockedIps::class)
        ->assertSet('data.text', "1.2.3.4 // office\n5.6.7.8")
        ->assertSee(__('panel.lists.count', ['count' => 2]));
})->with(['viewer', 'admin', 'owner']);

it('saves valid text and reports the counts', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(BlockedIps::class)
        ->fillForm(['text' => "1.2.3.4\n10.0.0.9/24\n1.2.3.4"])
        ->callAction('save')
        ->assertHasNoErrors()
        ->assertNotified(__('panel.lists.saved', ['added' => 2, 'removed' => 0, 'updated' => 0]))
        ->assertSet('data.text', "1.2.3.4\n10.0.0.0/24");
});

it('names invalid lines and saves nothing', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(BlockedIps::class)
        ->fillForm(['text' => "1.2.3.4\nnope"])
        ->callAction('save')
        ->assertHasErrors(['data.text'])
        ->assertSee(__('panel.lists.errors.line', ['line' => 2, 'reason' => __('panel.lists.errors.ip')]));

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

    $page->fillForm(['text' => '1.1.1.1'])
        ->callAction('save')
        ->assertNotified(__('panel.lists.changed_since'));

    expect(FilterListEntry::query()->pluck('value')->all())->toBe(['9.9.9.9']);
});

it('asks before removing the admin’s own IP from Allowed GM IPs', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    seedList(ListName::AllowedIps, "127.0.0.0/8\n8.8.8.8");

    Livewire::test(AllowedIps::class)
        ->fillForm(['text' => '8.8.8.8'])
        ->mountAction('save')
        ->assertMountedActionModalSee(__('panel.lists.own_ip_warning', ['ip' => '127.0.0.1']));

    Livewire::test(AllowedIps::class)
        ->fillForm(['text' => "127.0.0.1\n8.8.8.8"])
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
