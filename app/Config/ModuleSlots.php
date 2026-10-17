<?php

namespace App\Config;

use App\Enums\Filter\ModuleType;
use App\Models\Filter\FilterModule;
use Illuminate\Support\Facades\Gate;

/**
 * Adds Agent{n+1} or removes the highest-numbered agent, keeping Agent1..AgentN without gaps
 * (spec 003, FR-016). Writes go through ConfigStore, so they are audited and versioned.
 */
class ModuleSlots
{
    public function __construct(private readonly ConfigStore $store) {}

    public function addAgent(): FilterModule
    {
        Gate::authorize('create', FilterModule::class);

        $agents = $this->agents();

        if (count($agents) >= ModuleRules::MAX_AGENTS) {
            throw InvalidModuleSetupException::because([__('panel.modules.errors.max_agents', ['max' => ModuleRules::MAX_AGENTS])]);
        }

        $last = end($agents) ?: null;

        /** @var FilterModule */
        return $this->store->create(FilterModule::class, [
            'type' => ModuleType::Agent,
            'name' => 'Agent'.(count($agents) + 1),
            'module_ip' => $last->module_ip ?? '127.0.0.1',
            'module_port' => ($last->module_port ?? 15883) + 1,
            'guard_ip' => $last->guard_ip ?? '127.0.0.1',
            'guard_port' => ($last->guard_port ?? 15883) + 1,
            'enabled' => false,
        ]);
    }

    public function removeLastAgent(): void
    {
        $agents = $this->agents();
        $last = end($agents) ?: null;

        if ($last === null) {
            throw InvalidModuleSetupException::because([__('panel.modules.errors.last_agent')]);
        }

        Gate::authorize('delete', $last);

        if (count($agents) <= 1) {
            throw InvalidModuleSetupException::because([__('panel.modules.errors.last_agent')]);
        }

        $this->store->delete($last);
    }

    public function isLastAgent(FilterModule $module): bool
    {
        $agents = $this->agents();

        return count($agents) > 1 && end($agents)->is($module);
    }

    public function canAddAgent(): bool
    {
        return count($this->agents()) < ModuleRules::MAX_AGENTS;
    }

    /**
     * @return list<FilterModule> ordered Agent1..AgentN
     */
    private function agents(): array
    {
        return FilterModule::query()->where('type', ModuleType::Agent)->get()
            ->sortBy(fn (FilterModule $module): int => (int) substr($module->name, strlen('Agent')))
            ->values()
            ->all();
    }
}
