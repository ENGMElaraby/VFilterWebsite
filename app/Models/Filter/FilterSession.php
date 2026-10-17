<?php

namespace App\Models\Filter;

use Database\Factories\Filter\FilterSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One live player connection, written by the filter. Read-only for the website.
 *
 * @property int $id
 * @property string $module
 * @property string $ip
 * @property string|null $hwid
 * @property string|null $username
 * @property string|null $charname
 * @property Carbon $connected_at
 * @property Carbon|null $last_packet_at
 */
class FilterSession extends Model
{
    /** @use HasFactory<FilterSessionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'filter_sessions';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'last_packet_at' => 'datetime',
        ];
    }
}
