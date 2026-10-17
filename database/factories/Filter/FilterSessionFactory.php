<?php

namespace Database\Factories\Filter;

use App\Models\Filter\FilterSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterSession>
 */
class FilterSessionFactory extends Factory
{
    protected $model = FilterSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module' => 'Agent1',
            'ip' => fake()->ipv4(),
            'username' => fake()->userName(),
            'charname' => fake()->firstName(),
            'connected_at' => now('UTC'),
        ];
    }
}
