<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tâches planifiées (W02). En local : php artisan schedule:work dans une fenêtre à part.
 */
Schedule::command('koryhub:tokens-remind')->dailyAt('08:00');
Schedule::command('koryhub:device-codes-prune')->everyFifteenMinutes();
Schedule::command('koryhub:scores')->hourly();
Schedule::command('koryhub:certifications-remind')->dailyAt('09:00');
Schedule::command('koryhub:reports-remind')->dailyAt('09:00');
