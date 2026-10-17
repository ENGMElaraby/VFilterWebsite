<?php

namespace Database\Factories\Filter;

use App\Models\Filter\FilterListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterListEntry>
 */
class FilterListEntryFactory extends Factory
{
    protected $model = FilterListEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['list' => 'blocked_ips', 'value' => fake()->unique()->ipv4(), 'comment' => null];
    }
}
