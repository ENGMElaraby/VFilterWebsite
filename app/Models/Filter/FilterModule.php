<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\ModuleType;
use Database\Factories\Filter\FilterModuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One proxy listener (gateway, agent or download).
 *
 * @property int $id
 * @property ModuleType $type
 * @property string $name
 * @property string $module_ip
 * @property int $module_port
 * @property string $guard_ip
 * @property int $guard_port
 * @property bool $enabled
 */
class FilterModule extends Model implements ConfigurationModel
{
    /** @use HasFactory<FilterModuleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'filter_modules';

    protected $guarded = ['id'];

    public static function configArea(): ConfigArea
    {
        return ConfigArea::Modules;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ModuleType::class,
            'module_port' => 'integer',
            'guard_port' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
