<?php

use App\Enums\Filter\CommandName;
use App\Enums\Filter\ConfigArea;
use App\Filament\Resources\FilterModules\Pages\EditFilterModule;
use App\Filament\Resources\FilterModules\Pages\ListFilterModules;
use App\Models\AuditLog;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Livewire\Livewire;

function module(string $name): FilterModule
{
    return FilterModule::query()->where('name', $name)->sole();
}

beforeEach(fn () => seedFilterModules(agents: 2));

describe('admins', function () {
    beforeEach(fn () => $this->signIn(User::factory()->admin()->withTwoFactor()->create()));

    it('lists the modules in order with their addresses', function () {
        Livewire::test(ListFilterModules::class)
            ->assertCanSeeTableRecords([module('Gateway'), module('Download'), module('Agent1'), module('Agent2')], inOrder: true)
            ->assertSee('10.0.0.10');
    });

    it('saves a valid change, audited and versioned, and offers a restart', function () {
        $before = (int) FilterConfigVersion::query()->whereKey(ConfigArea::Modules->value)->value('version');

        Livewire::test(EditFilterModule::class, ['record' => module('Agent1')->getKey()])
            ->fillForm(['guard_port' => 25884])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('panel.modules.restart_needed', ['name' => 'Agent1']));

        $entry = AuditLog::query()->where('action', 'modules.updated')->sole();
        expect(module('Agent1')->guard_port)->toBe(25884)
            ->and($entry->old_values)->toBe(['guard_port' => 15884])
            ->and($entry->new_values)->toBe(['guard_port' => 25884])
            ->and((int) FilterConfigVersion::query()->whereKey(ConfigArea::Modules->value)->value('version'))->toBe($before + 1);
    });

    it('queues the restart from the shortcut', function () {
        Livewire::test(EditFilterModule::class, ['record' => module('Agent1')->getKey()])
            ->callAction('restartModule');

        expect(FilterCommand::query()->sole())->command->toBe(CommandName::Restart)->target->toBe('agent1');
    });

    it('rejects bad ports and addresses', function (array $data, string $field) {
        Livewire::test(EditFilterModule::class, ['record' => module('Agent1')->getKey()])
            ->fillForm($data)
            ->call('save')
            ->assertHasFormErrors([$field]);

        expect(module('Agent1')->guard_port)->toBe(15884)->and(module('Agent1')->module_ip)->toBe('10.0.0.10');
    })->with([
        'port 0' => [['guard_port' => 0], 'guard_port'],
        'port 70000' => [['module_port' => 70000], 'module_port'],
        'bad host' => [['module_ip' => 'a..b'], 'module_ip'],
    ]);

    it('rejects a guard address and port another module uses', function () {
        Livewire::test(EditFilterModule::class, ['record' => module('Agent2')->getKey()])
            ->fillForm(['guard_port' => 15884])
            ->call('save')
            ->assertHasFormErrors(['guard_port']);

        expect(module('Agent2')->guard_port)->toBe(15885);
    });

    it('adds agents up to five and removes only the last one', function () {
        Livewire::test(ListFilterModules::class)
            ->assertTableActionHidden('removeAgent', module('Agent1'))
            ->assertTableActionVisible('removeAgent', module('Agent2'))
            ->callAction('addAgent')
            ->callAction('addAgent')
            ->callAction('addAgent');

        expect(FilterModule::query()->where('type', 'agent')->count())->toBe(5);
        Livewire::test(ListFilterModules::class)
            ->assertActionHidden('addAgent')
            ->callTableAction('removeAgent', module('Agent5'));
        expect(FilterModule::query()->where('name', 'Agent5')->exists())->toBeFalse();
    });
});

describe('viewers', function () {
    beforeEach(fn () => $this->signIn(User::factory()->viewer()->withTwoFactor()->create()));

    it('see guard addresses but not real module addresses', function () {
        Livewire::test(ListFilterModules::class)
            ->assertSee('203.0.113.10')
            ->assertDontSee('10.0.0.10')
            ->assertSee(__('panel.modules.masked'))
            ->assertActionHidden('addAgent')
            ->assertTableActionHidden('edit', module('Agent1'))
            ->assertTableActionHidden('removeAgent', module('Agent2'));
    });

    it('cannot open the edit page', function () {
        $this->get(route('filament.admin.resources.filter-modules.edit', ['record' => module('Agent1')]))->assertForbidden();
    });
});
