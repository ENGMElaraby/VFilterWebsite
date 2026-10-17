<?php

use App\Enums\Filter\RestartScope;
use App\Enums\Filter\SettingType;
use App\Models\AuditLog;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterSetting;
use App\Models\User;
use App\Settings\InvalidSettingValueException;
use App\Settings\SettingsRepository;
use App\Settings\UnknownSettingException;

beforeEach(function () {
    $this->settings = app(SettingsRepository::class);
    $this->settings->define('server.start_in_inspection', SettingType::Bool, 'server', false, RestartScope::Filter);
    $this->settings->define('logs.retention_days', SettingType::Int, 'logs', 30, RestartScope::None);
    $this->settings->define('ui.color', SettingType::Color, 'ui', '#FFFFFF', RestartScope::Client);
    AuditLog::query()->getQuery()->delete();
    FilterConfigVersion::query()->delete();
    $this->admin = $this->signIn(User::factory()->admin()->withTwoFactor()->create());
});

function settingsVersion(): int
{
    return (int) FilterConfigVersion::find('settings')?->version;
}

it('reads typed values', function () {
    expect($this->settings->get('server.start_in_inspection'))->toBeFalse()
        ->and($this->settings->get('logs.retention_days'))->toBe(30)
        ->and($this->settings->all('logs'))->toBe(['logs.retention_days' => 30]);
});

it('saves changes, bumps the settings counter once and audits each key', function () {
    $changed = $this->settings->set([
        'server.start_in_inspection' => true,
        'logs.retention_days' => 60,
        'ui.color' => '#FFFFFF',
    ]);

    expect($changed)->toBe(2)
        ->and($this->settings->get('server.start_in_inspection'))->toBeTrue()
        ->and($this->settings->get('logs.retention_days'))->toBe(60)
        ->and(settingsVersion())->toBe(1)
        ->and(FilterSetting::find('logs.retention_days')->updated_by)->toBe($this->admin->id);

    $entries = AuditLog::where('action', 'setting.updated')->orderBy('target_id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->target_id)->toBe('logs.retention_days')
        ->and($entries[0]->old_values)->toBe(['value' => 30])
        ->and($entries[0]->new_values)->toBe(['value' => 60])
        ->and($entries[1]->old_values)->toBe(['value' => false])
        ->and($entries[1]->actor_id)->toBe($this->admin->id);
});

it('writes nothing when nothing changed', function () {
    expect($this->settings->set(['logs.retention_days' => 30]))->toBe(0)
        ->and(settingsVersion())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

it('refuses unknown keys and wrong types without writing anything', function () {
    expect(fn () => $this->settings->set(['nope.key' => 1, 'logs.retention_days' => 60]))
        ->toThrow(UnknownSettingException::class);

    try {
        $this->settings->set(['logs.retention_days' => 'sixty', 'ui.color' => 'red', 'server.start_in_inspection' => true]);
    } catch (InvalidSettingValueException $exception) {
        expect(array_keys($exception->errors()))->toBe(['logs.retention_days', 'ui.color']);
    }

    expect($this->settings->get('logs.retention_days'))->toBe(30)
        ->and($this->settings->get('server.start_in_inspection'))->toBeFalse()
        ->and(settingsVersion())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

it('rolls back the value, the counter and the audit entry together', function () {
    AuditLog::creating(function (): void {
        throw new RuntimeException('audit write failed');
    });

    expect(fn () => $this->settings->set(['logs.retention_days' => 60]))->toThrow(RuntimeException::class);

    AuditLog::flushEventListeners();

    expect($this->settings->get('logs.retention_days'))->toBe(30)
        ->and(settingsVersion())->toBe(0);
});

it('adds missing settings without overwriting existing ones', function () {
    $this->settings->set(['logs.retention_days' => 90]);

    expect($this->settings->define('logs.retention_days', SettingType::Int, 'logs', 30, RestartScope::None))->toBeFalse()
        ->and($this->settings->get('logs.retention_days'))->toBe(90)
        ->and($this->settings->define('limits.ip', SettingType::Int, 'limits', 0, RestartScope::None))->toBeTrue()
        ->and($this->settings->get('limits.ip'))->toBe(0);
});
