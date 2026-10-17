<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use Database\Factories\Filter\FilterAreaRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Do or block X in area Y". Area = Region ID, World ID, ContinentName or -1 (everywhere).
 *
 * @property int $id
 * @property string $rule
 * @property string $area
 * @property string|null $target
 * @property array<string, mixed>|null $params
 * @property bool $enabled
 */
class FilterAreaRule extends Model implements ConfigurationModel
{
    /** @use HasFactory<FilterAreaRuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'filter_area_rules';

    protected $guarded = ['id'];

    public static function configArea(): ConfigArea
    {
        return ConfigArea::AreaRules;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
