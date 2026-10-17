<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use Illuminate\Database\Eloquent\Model;

/**
 * Game Assistant: which GM commands one account may use.
 *
 * @property string $username
 */
class FilterGaPermission extends Model implements ConfigurationModel
{
    public const COMMANDS = [
        'make_item', 'zoe', 'load_monster', 'mobkill0', 'mobkill1', 'notice', 'filter_cmd', 'invisible',
        'invincible', 'ban', 'recall_user', 'recall_guild', 'move_to_user', 'move_to_npc', 'to_town',
        'go_town', 'reset_q', 'wp', 'gm_skill', 'world_status',
    ];

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'filter_ga_permissions';

    protected $primaryKey = 'username';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function configArea(): ConfigArea
    {
        return ConfigArea::GaPermissions;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_fill_keys(self::COMMANDS, 'boolean');
    }
}
