<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;
use Database\Factories\Filter\FilterListEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One value in a named list (allowed IPs, blocked HWIDs, ...).
 *
 * @property int $id
 * @property string $list
 * @property string $value
 * @property string|null $comment
 */
class FilterListEntry extends Model implements ConfigurationModel
{
    /** @use HasFactory<FilterListEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'filter_lists';

    protected $guarded = ['id'];

    public static function configArea(): ConfigArea
    {
        return ConfigArea::Lists;
    }
}
