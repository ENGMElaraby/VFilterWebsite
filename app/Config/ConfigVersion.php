<?php

namespace App\Config;

use App\Enums\Filter\ConfigArea;
use App\Models\Filter\FilterConfigVersion;

/**
 * Per-area change counters the filter polls (shared contract rule 4).
 * Call bump() inside the same transaction as the change it announces.
 */
class ConfigVersion
{
    public function bump(ConfigArea $area): void
    {
        $now = now('UTC');

        // One atomic statement, so concurrent saves never lose an increment.
        $updated = FilterConfigVersion::query()
            ->whereKey($area->value)
            ->update(['version' => FilterConfigVersion::query()->raw('version + 1'), 'updated_at' => $now]);

        if ($updated === 0) {
            FilterConfigVersion::query()->create(['area' => $area, 'version' => 1, 'updated_at' => $now]);
        }
    }

    /**
     * @return array<string, int> area => version
     */
    public function current(): array
    {
        return FilterConfigVersion::query()
            ->orderBy('area')
            ->get()
            ->mapWithKeys(fn (FilterConfigVersion $row): array => [$row->area->value => $row->version])
            ->all();
    }
}
