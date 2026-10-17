<?php

use App\Filament\Resources\GameAssistants\Pages\ListGameAssistants;
use App\Lists\GameAssistants;
use App\Models\Filter\FilterGaPermission;
use App\Models\User;
use Livewire\Livewire;

function helper(): FilterGaPermission
{
    return FilterGaPermission::query()->findOrFail('helper1');
}

it('lets admins add, toggle, switch all and remove', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(ListGameAssistants::class)
        ->callAction('add', data: ['username' => 'helper1'])
        ->assertHasNoActionErrors()
        ->assertCanSeeTableRecords([helper()])
        ->assertTableColumnExists('world_status')
        ->call('updateTableColumnState', 'notice', 'helper1', true);

    expect(helper()->notice)->toBeTrue()->and(helper()->ban)->toBeFalse();

    Livewire::test(ListGameAssistants::class)->callTableAction('allOn', helper());
    expect(helper()->ban)->toBeTrue()->and(helper()->make_item)->toBeTrue();

    Livewire::test(ListGameAssistants::class)->callTableAction('allOff', helper());
    expect(helper()->notice)->toBeFalse();

    Livewire::test(ListGameAssistants::class)->callTableAction('remove', helper());
    expect(FilterGaPermission::count())->toBe(0);
});

it('refuses a duplicate assistant', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    app(GameAssistants::class)->add('helper1');

    Livewire::test(ListGameAssistants::class)
        ->callAction('add', data: ['username' => 'HELPER1'])
        ->assertNotified(__('panel.assistants.exists', ['username' => 'HELPER1']));

    expect(FilterGaPermission::count())->toBe(1);
});

it('is read-only for viewers', function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    app(GameAssistants::class)->add('helper1');
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    Livewire::test(ListGameAssistants::class)
        ->assertActionHidden('add')
        ->assertTableActionHidden('allOn', helper())
        ->assertTableActionHidden('remove', helper())
        ->call('updateTableColumnState', 'ban', 'helper1', true);

    expect(helper()->ban)->toBeFalse();
});
