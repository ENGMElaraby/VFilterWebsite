<?php

use App\Config\ConfigVersion;
use App\Enums\Filter\ConfigArea;
use App\Models\Filter\FilterConfigVersion;
use Illuminate\Support\Carbon;

it('increases only the given area by one', function () {
    Carbon::setTestNow('2026-10-17 12:00:00');
    FilterConfigVersion::query()->create(['area' => ConfigArea::Settings, 'version' => 5]);
    FilterConfigVersion::query()->create(['area' => ConfigArea::Lists, 'version' => 2]);

    app(ConfigVersion::class)->bump(ConfigArea::Settings);

    expect(app(ConfigVersion::class)->current())->toBe(['lists' => 2, 'settings' => 6])
        ->and(FilterConfigVersion::find('settings')->updated_at->toDateTimeString())->toBe('2026-10-17 12:00:00');
});

it('recreates a missing area row', function () {
    app(ConfigVersion::class)->bump(ConfigArea::Modules);
    app(ConfigVersion::class)->bump(ConfigArea::Modules);

    expect(FilterConfigVersion::find('modules')->version)->toBe(2);
});
