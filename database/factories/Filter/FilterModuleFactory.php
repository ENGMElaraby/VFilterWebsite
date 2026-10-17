<?php

namespace Database\Factories\Filter;

use App\Enums\Filter\ModuleType;
use App\Models\Filter\FilterModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterModule>
 */
class FilterModuleFactory extends Factory
{
    protected $model = FilterModule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ModuleType::Agent,
            'name' => 'Agent1',
            'module_ip' => '10.0.0.10',
            'module_port' => 15884,
            'guard_ip' => '203.0.113.10',
            'guard_port' => 15884,
            'enabled' => true,
        ];
    }
}
