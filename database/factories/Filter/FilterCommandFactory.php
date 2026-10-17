<?php

namespace Database\Factories\Filter;

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use App\Models\Filter\FilterCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterCommand>
 */
class FilterCommandFactory extends Factory
{
    protected $model = FilterCommand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'command' => CommandName::Restart,
            'target' => 'agent1',
            'payload' => null,
            'status' => CommandStatus::Pending,
            'created_by' => 1,
            'created_at' => now('UTC'),
        ];
    }
}
