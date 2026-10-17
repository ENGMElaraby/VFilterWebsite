<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Change counter per configuration area. Bumped only by App\Config\ConfigVersion.
 *
 * @property ConfigArea $area
 * @property int $version
 * @property Carbon|null $updated_at
 */
class FilterConfigVersion extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'filter_config_version';

    protected $primaryKey = 'area';

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => ConfigArea::class,
            'version' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}
