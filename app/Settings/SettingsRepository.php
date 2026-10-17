<?php

namespace App\Settings;

use App\Config\ConfigVersion;
use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\RestartScope;
use App\Enums\Filter\SettingType;
use App\Models\Filter\FilterSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

/**
 * The only way to read and write filter_settings (contracts/config-write-api.md):
 * typed values, validated before writing, audited per key, `settings` counter bumped once per save.
 */
class SettingsRepository
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ConfigVersion $version,
    ) {}

    public function get(string $key): mixed
    {
        $setting = FilterSetting::query()->find($key) ?? throw UnknownSettingException::forKeys([$key]);

        return $setting->typedValue();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?string $group = null): array
    {
        return FilterSetting::query()
            ->when($group, fn ($query) => $query->where('group', $group))
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (FilterSetting $setting): array => [$setting->key => $setting->typedValue()])
            ->all();
    }

    /**
     * Save several settings at once. Returns how many actually changed.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): int
    {
        $settings = FilterSetting::query()->whereKey(array_keys($values))->get()->keyBy('key');

        $unknown = array_values(array_diff(array_keys($values), $settings->keys()->all()));
        if ($unknown !== []) {
            throw UnknownSettingException::forKeys($unknown);
        }

        $this->assertValid($settings->all(), $values);

        return $this->audit->transaction(function () use ($settings, $values): int {
            $changed = 0;

            foreach ($values as $key => $value) {
                $changed += $this->write($settings[$key], $value) ? 1 : 0;
            }

            if ($changed > 0) {
                $this->version->bump(ConfigArea::Settings);
            }

            return $changed;
        });
    }

    /**
     * Add a setting if it does not exist yet. Never overwrites. Returns true when added.
     */
    public function define(string $key, SettingType $type, string $group, mixed $default, RestartScope $restart): bool
    {
        if (FilterSetting::query()->whereKey($key)->exists()) {
            return false;
        }

        if (! $type->isValid($default)) {
            throw new InvalidSettingValueException([$key => "Default does not match type {$type->value}."]);
        }

        FilterSetting::query()->create([
            'key' => $key,
            'value' => $type->serialize($default),
            'value_type' => $type,
            'group' => $group,
            'requires_restart' => $restart,
            'updated_at' => now('UTC'),
        ]);

        return true;
    }

    /**
     * @param  array<string, FilterSetting>  $settings
     * @param  array<string, mixed>  $values
     */
    private function assertValid(array $settings, array $values): void
    {
        $errors = [];

        foreach ($values as $key => $value) {
            $type = $settings[$key]->value_type;

            if (! $type->isValid($value)) {
                $errors[$key] = "Expected a value of type {$type->value}.";
            }
        }

        if ($errors !== []) {
            throw new InvalidSettingValueException($errors);
        }
    }

    private function write(FilterSetting $setting, mixed $value): bool
    {
        $old = $setting->typedValue();
        $serialized = $setting->value_type->serialize($value);

        if ($serialized === $setting->value) {
            return false;
        }

        $actor = Auth::user();

        $setting->forceFill([
            'value' => $serialized,
            'updated_at' => now('UTC'),
            'updated_by' => $actor instanceof User ? $actor->getKey() : null,
        ])->save();

        $this->audit->log('setting.updated', $setting, ['value' => $old], ['value' => $setting->typedValue()]);

        return true;
    }
}
