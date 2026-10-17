<?php

namespace App\Models\Filter;

use App\Enums\Filter\LogCategory;
use App\Enums\Filter\LogLevel;
use Database\Factories\Filter\FilterLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One event logged by the filter. Read-only for the website (pruned by retention later).
 *
 * @property int $id
 * @property Carbon $ts
 * @property LogLevel $level
 * @property string $module
 * @property LogCategory $category
 * @property string|null $ip
 * @property string|null $hwid
 * @property string|null $username
 * @property string|null $charname
 * @property string|null $opcode
 * @property string $message
 * @property array<string, mixed>|null $data
 */
class FilterLog extends Model
{
    /** @use HasFactory<FilterLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'filter_logs';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ts' => 'datetime',
            'level' => LogLevel::class,
            'category' => LogCategory::class,
            'data' => 'array',
        ];
    }
}
