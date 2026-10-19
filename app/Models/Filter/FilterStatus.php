<?php

namespace App\Models\Filter;

use App\Enums\Filter\GatewayMode;
use App\Enums\Filter\ModuleState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Heartbeat row per module, written by the filter. Read-only for the website.
 *
 * @property string $module
 * @property ModuleState $state
 * @property int $online_count
 * @property int $connections
 * @property GatewayMode|null $gateway_mode
 * @property string|null $version
 * @property Carbon|null $started_at
 * @property Carbon|null $heartbeat_at
 * @property string|null $last_error
 * @property array<string, mixed>|null $details
 */
class FilterStatus extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'filter_status';

    protected $primaryKey = 'module';

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ModuleState::class,
            'gateway_mode' => GatewayMode::class,
            'online_count' => 'integer',
            'connections' => 'integer',
            'started_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'details' => 'array',
        ];
    }
}
