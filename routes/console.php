<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec 004: keep filter_logs within logs.retention_days. The server must run `schedule:run` every minute.
Schedule::command('vfilter:prune-logs')->dailyAt('03:30')->withoutOverlapping();
