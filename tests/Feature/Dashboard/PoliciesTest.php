<?php

use App\Enums\Filter\CommandStatus;
use App\Models\Filter\FilterCommand;
use App\Models\Filter\FilterModule;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets admins and the owner control the filter and nobody else', function (string $role, bool $allowed) {
    $user = User::factory()->{$role}()->create();
    $pending = FilterCommand::factory()->create(['status' => CommandStatus::Pending]);
    $module = FilterModule::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', FilterCommand::class))->toBeTrue()
        ->and($gate->allows('create', FilterCommand::class))->toBe($allowed)
        ->and($gate->allows('cancel', $pending))->toBe($allowed)
        ->and($gate->allows('viewAny', FilterModule::class))->toBeTrue()
        ->and($gate->allows('view', $module))->toBeTrue()
        ->and($gate->allows('create', FilterModule::class))->toBe($allowed)
        ->and($gate->allows('update', $module))->toBe($allowed)
        ->and($gate->allows('delete', $module))->toBe($allowed)
        ->and($gate->allows('viewAddresses', FilterModule::class))->toBe($allowed);
})->with([
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'viewer' => ['viewer', false],
]);

it('leaves the pending check to the command queue', function () {
    $admin = User::factory()->admin()->create();
    $running = FilterCommand::factory()->create(['status' => CommandStatus::Running]);

    // CommandQueue::cancel() refuses it with CommandAlreadyStartedException (CancelCommandTest).
    expect(Gate::forUser($admin)->allows('cancel', $running))->toBeTrue();
});
