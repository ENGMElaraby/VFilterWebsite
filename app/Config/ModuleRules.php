<?php

namespace App\Config;

use App\Enums\Filter\ModuleType;
use App\Models\Filter\FilterModule;
use Illuminate\Support\Collection;

/**
 * Valid module setup: exactly 1 gateway, exactly 1 download, 1-5 agents named Agent1..AgentN with
 * no gaps, ports 1-65535, addresses that are IPs or host names, no shared guard address and port
 * (shared contract, filter_modules; spec 003).
 */
class ModuleRules
{
    public const MAX_AGENTS = 5;

    public const MIN_PORT = 1;

    public const MAX_PORT = 65535;

    private const HOSTNAME_PATTERN = '/^(?=.{1,253}$)([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

    public function assertValid(): void
    {
        $modules = FilterModule::query()->get();

        $problems = [
            ...$this->countProblems($modules),
            ...$this->agentNameProblems($modules),
            ...$this->addressProblems($modules),
        ];

        if ($problems !== []) {
            throw InvalidModuleSetupException::because($problems);
        }

        $clashes = $this->guardClashProblems($modules);

        if ($clashes !== []) {
            throw GuardAddressClashException::because($clashes);
        }
    }

    /**
     * @param  Collection<int, FilterModule>  $modules
     * @return list<string>
     */
    private function countProblems(Collection $modules): array
    {
        $count = fn (ModuleType $type): int => $modules->where('type', $type)->count();
        $problems = [];

        if ($count(ModuleType::Gateway) !== 1) {
            $problems[] = 'Exactly 1 gateway module is required.';
        }

        if ($count(ModuleType::Download) !== 1) {
            $problems[] = 'Exactly 1 download module is required.';
        }

        $agents = $count(ModuleType::Agent);
        if ($agents < 1 || $agents > self::MAX_AGENTS) {
            $problems[] = 'Between 1 and '.self::MAX_AGENTS.' agent modules are required.';
        }

        return $problems;
    }

    /**
     * @param  Collection<int, FilterModule>  $modules
     * @return list<string>
     */
    private function agentNameProblems(Collection $modules): array
    {
        $names = $modules->where('type', ModuleType::Agent)->pluck('name')->sort()->values()->all();
        $expected = array_map(fn (int $i): string => 'Agent'.$i, range(1, max(1, count($names))));

        return $names === [] || $names === $expected
            ? []
            : ['Agent modules must be named Agent1 to AgentN without gaps.'];
    }

    /**
     * @param  Collection<int, FilterModule>  $modules
     * @return list<string>
     */
    private function addressProblems(Collection $modules): array
    {
        $problems = [];

        foreach ($modules as $module) {
            foreach (['module_port', 'guard_port'] as $field) {
                if ($module->{$field} < self::MIN_PORT || $module->{$field} > self::MAX_PORT) {
                    $problems[] = "{$module->name}: {$field} must be a port between 1 and 65535.";
                }
            }

            foreach (['module_ip', 'guard_ip'] as $field) {
                if (! $this->isAddress($module->{$field})) {
                    $problems[] = "{$module->name}: {$field} must be an IP address or host name.";
                }
            }
        }

        return $problems;
    }

    /**
     * The filter cannot listen twice on one guard address and port (spec 003, FR-017).
     *
     * @param  Collection<int, FilterModule>  $modules
     * @return list<string>
     */
    private function guardClashProblems(Collection $modules): array
    {
        return $modules
            ->groupBy(fn (FilterModule $module): string => strtolower($module->guard_ip).':'.$module->guard_port)
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): string => __('panel.modules.errors.guard_clash', [
                'first' => $group->first()->name,
                'second' => $group->skip(1)->first()->name,
            ]))
            ->values()
            ->all();
    }

    public function isAddress(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false
            || preg_match(self::HOSTNAME_PATTERN, $value) === 1;
    }
}
