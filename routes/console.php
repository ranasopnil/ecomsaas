<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Merchants point their own domain at us and wait. This looks every few
 * minutes to see whether it has started resolving here yet.
 */
Schedule::command('domains:check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
