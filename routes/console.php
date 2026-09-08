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

/*
 * Trials end, months run out, days of grace expire. This is what notices.
 * It charges nobody — the money moves by hand and staff confirm it — but it
 * does close the dashboard of a shop that has not paid, seven days after its
 * renewal date. The shop's own storefront is never touched.
 */
Schedule::command('subscriptions:tick')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * NOT SCHEDULED YET, ON PURPOSE.
 *
 * `payments:reconcile` asks each gateway about payments the customer never
 * came back from, and finishes the orders waiting on them. It can only ever
 * find money, never lose it — but it does change orders on its own, and the
 * owner has not yet said it should run unattended. Run it by hand until then:
 *
 *     php artisan payments:reconcile
 *
 * To switch it on, put this back:
 *
 *     Schedule::command('payments:reconcile')
 *         ->everyTenMinutes()
 *         ->withoutOverlapping()
 *         ->runInBackground();
 */

/*
 * The "here right now" figure leaves a short-lived fingerprint per visitor.
 * This throws away the ones that are more than a day old.
 */
Schedule::command('visits:tidy')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
