<?php

namespace App\Models\Filter;

use App\Enums\Filter\ConfigArea;

/**
 * A filter configuration row. Written only through App\Config\ConfigStore.
 */
interface ConfigurationModel
{
    public static function configArea(): ConfigArea;
}
