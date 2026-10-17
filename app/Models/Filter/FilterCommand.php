<?php

namespace App\Models\Filter;

use App\Enums\Filter\CommandName;
use App\Enums\Filter\CommandStatus;
use Database\Factories\Filter\FilterCommandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One website -> filter instruction. The website inserts it; the filter sets status/result.
 *
 * @property int $id
 * @property CommandName $command
 * @property string $target
 * @property array<string, mixed>|null $payload
 * @property CommandStatus $status
 * @property string|null $result
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon|null $processed_at
 */
class FilterCommand extends Model
{
    /** @use HasFactory<FilterCommandFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'filter_commands';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'command' => CommandName::class,
            'status' => CommandStatus::class,
            'payload' => 'array',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
