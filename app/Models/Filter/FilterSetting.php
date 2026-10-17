<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use App\Enums\Filter\RestartScope;
use App\Enums\Filter\SettingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One filter switch. Read and written through App\Settings\SettingsRepository.
 *
 * @property string $key
 * @property string $value
 * @property SettingType $value_type
 * @property string $group
 * @property RestartScope $requires_restart
 * @property Carbon|null $updated_at
 * @property int|null $updated_by
 */
class FilterSetting extends Model implements ConfigurationModel
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'filter_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function configArea(): ConfigArea
    {
        return ConfigArea::Settings;
    }

    public function typedValue(): mixed
    {
        return $this->value_type->cast($this->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_type' => SettingType::class,
            'requires_restart' => RestartScope::class,
            'updated_at' => 'datetime',
            'updated_by' => 'integer',
        ];
    }
}
