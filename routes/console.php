<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('mindwell:notify')->everyFiveMinutes();

// Unfinished Mobile Money payments (2026-11-04): late approvals, closed browsers, missed
// callbacks. See App\Console\Commands\PaymentsReconcile.
Schedule::command('mindwell:payments-reconcile')->everyMinute()->withoutOverlapping();
