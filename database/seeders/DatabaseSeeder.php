<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Default filter configuration only. Panel users are created with `php artisan vfilter:create-owner`.
     */
    public function run(): void
    {
        Artisan::call('vfilter:install-defaults');
    }
}
