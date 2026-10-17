<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\CooldownAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Cooldown in seconds (0-32000, 0 = none) for one action.
 *
 * @property CooldownAction $action
 * @property int $seconds
 */
class FilterCooldown extends Model implements ConfigurationModel
{
    public const MAX_SECONDS = 32000;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'filter_cooldowns';

    protected $primaryKey = 'action';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function configArea(): ConfigArea
    {
        return ConfigArea::Cooldowns;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => CooldownAction::class,
            'seconds' => 'integer',
        ];
    }
}
