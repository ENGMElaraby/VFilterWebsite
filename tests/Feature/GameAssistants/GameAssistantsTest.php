<?php

use App\Enums\Filter\ConfigArea;
use App\Lists\GameAssistants;
use App\Models\AuditLog;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterGaPermission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(fn () => $this->signIn(User::factory()->admin()->withTwoFactor()->create()));

it('adds an assistant with every command off', function () {
    app(GameAssistants::class)->add('helper1');

    $row = FilterGaPermission::query()->sole();
    expect($row->username)->toBe('helper1');
    foreach (FilterGaPermission::COMMANDS as $command) {
        expect($row->{$command})->toBeFalse();
    }
    expect(AuditLog::query()->where('action', 'ga_permissions.created')->count())->toBe(1);
});

it('refuses bad or duplicate usernames', function (string $username) {
    app(GameAssistants::class)->add('Helper1');

    expect(fn () => app(GameAssistants::class)->add($username))->toThrow(InvalidArgumentException::class);
    expect(FilterGaPermission::count())->toBe(1);
})->with(['helper1', '', 'bad name', str_repeat('a', 33)]);

it('changes only the given permissions, audited and versioned', function () {
    app(GameAssistants::class)->add('helper1');
    $before = (int) FilterConfigVersion::query()->whereKey(ConfigArea::GaPermissions->value)->value('version');

    app(GameAssistants::class)->set('helper1', ['notice' => true, 'to_town' => true, 'ban' => false]);

    $row = FilterGaPermission::query()->sole();
    $entry = AuditLog::query()->where('action', 'ga_permissions.updated')->sole();
    expect($row->notice)->toBeTrue()->and($row->to_town)->toBeTrue()->and($row->ban)->toBeFalse()
        ->and($entry->new_values)->toBe(['notice' => true, 'to_town' => true])
        ->and((int) FilterConfigVersion::query()->whereKey(ConfigArea::GaPermissions->value)->value('version'))->toBe($before + 1);
});

it('ignores unknown command names', function () {
    app(GameAssistants::class)->add('helper1');

    expect(fn () => app(GameAssistants::class)->set('helper1', ['drop_table' => true]))->toThrow(InvalidArgumentException::class);
});

it('removes an assistant', function () {
    app(GameAssistants::class)->add('helper1');
    app(GameAssistants::class)->remove('helper1');

    expect(FilterGaPermission::count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'ga_permissions.deleted')->count())->toBe(1);
});

it('refuses viewers', function () {
    app(GameAssistants::class)->add('helper1');
    $this->signIn(User::factory()->viewer()->withTwoFactor()->create());

    expect(fn () => app(GameAssistants::class)->add('helper2'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(GameAssistants::class)->set('helper1', ['ban' => true]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(GameAssistants::class)->remove('helper1'))->toThrow(AuthorizationException::class);
});
