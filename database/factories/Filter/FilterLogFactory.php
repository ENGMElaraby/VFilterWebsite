<?php

namespace Database\Factories\Filter;

use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use App\Models\Filter\FilterLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterLog>
 */
class FilterLogFactory extends Factory
{
    protected $model = FilterLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ts' => now('UTC'),
            'level' => LogLevel::Info,
            'module' => 'Agent1',
            'category' => LogCategory::Connection,
            'ip' => fake()->ipv4(),
            'message' => 'Client connected',
        ];
    }
}
