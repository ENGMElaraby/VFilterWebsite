<?php

namespace App\Console\Commands;

use App\Config\ConfigStore;
use App\Config\ConfigVersion;
use App\Config\FilterDefaults;
use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\CooldownAction;
use App\Models\Filter\FilterConfigVersion;
use App\Models\Filter\FilterCooldown;
use App\Models\Filter\FilterModule;
use App\Services\AuditLogger;
use App\Settings\SettingsRepository;
use Illuminate\Console\Command;

/**
 * Adds missing default configuration rows. Never overwrites; safe to run any time.
 */
class InstallDefaults extends Command
{
    protected $signature = 'vfilter:install-defaults';

    protected $description = 'Add missing default filter configuration (modules, cooldowns, core settings)';

    public function handle(AuditLogger $audit, ConfigStore $store, SettingsRepository $settings, ConfigVersion $version): int
    {
        $added = $audit->transaction(function () use ($audit, $store, $settings, $version): array {
            $this->ensureCounterRows();

            $added = [
                'modules' => $this->addModules($store),
                'cooldowns' => $this->addCooldowns($store),
                'settings' => $this->addSettings($settings),
            ];

            if ($added['settings'] > 0) {
                $version->bump(ConfigArea::Settings);
            }

            if (array_sum($added) > 0) {
                $audit->asSystem()->log('config.defaults_installed', new: $added);
            }

            return $added;
        });

        $this->info(array_sum($added).' rows added ('.http_build_query($added, arg_separator: ', ').').');

        return self::SUCCESS;
    }

    private function ensureCounterRows(): void
    {
        foreach (ConfigArea::cases() as $area) {
            FilterConfigVersion::query()->firstOrCreate(['area' => $area->value], ['version' => 0]);
        }
    }

    private function addModules(ConfigStore $store): int
    {
        $missing = array_filter(
            FilterDefaults::modules(),
            fn (array $module): bool => ! FilterModule::query()->where('name', $module['name'])->exists(),
        );

        if ($missing === []) {
            return 0;
        }

        $store->batch(function (ConfigStore $store) use ($missing): void {
            foreach ($missing as $module) {
                $store->create(FilterModule::class, $module);
            }
        });

        return count($missing);
    }

    private function addCooldowns(ConfigStore $store): int
    {
        $existing = FilterCooldown::query()->pluck('action')->map(fn (CooldownAction $action): string => $action->value)->all();
        $missing = array_filter(CooldownAction::cases(), fn (CooldownAction $action): bool => ! in_array($action->value, $existing, true));

        if ($missing === []) {
            return 0;
        }

        $store->batch(function (ConfigStore $store) use ($missing): void {
            foreach ($missing as $action) {
                $store->create(FilterCooldown::class, ['action' => $action, 'seconds' => 0]);
            }
        });

        return count($missing);
    }

    private function addSettings(SettingsRepository $settings): int
    {
        $added = 0;

        foreach (FilterDefaults::settings() as [$key, $type, $group, $default, $restart]) {
            $added += $settings->define($key, $type, $group, $default, $restart) ? 1 : 0;
        }

        return $added;
    }
}
