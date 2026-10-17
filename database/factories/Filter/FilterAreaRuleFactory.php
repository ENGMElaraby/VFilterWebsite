<?php

namespace Database\Factories\Filter;

use App\Models\Filter\FilterAreaRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterAreaRule>
 */
class FilterAreaRuleFactory extends Factory
{
    protected $model = FilterAreaRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['rule' => 'pk.block', 'area' => '-1', 'target' => null, 'params' => null, 'enabled' => true];
    }
}
