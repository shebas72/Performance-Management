<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:expire')->hourly();

Schedule::command('notifications:remind-actuals')->dailyAt('09:00');
Schedule::command('billing:trial-reminders')->dailyAt('09:00'); // SaaS only; skip this line in the standalone package
