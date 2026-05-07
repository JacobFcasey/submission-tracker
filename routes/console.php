<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// CAPS reference data sync (Layer 1)
//
// Pulls canonical Company / Municipality / Member / Policy master data from
// CAPS every 4 hours. Runs in the background so the schedule worker stays
// responsive, and uses withoutOverlapping() so a slow CAPS response cannot
// stack up multiple concurrent syncs. Output is appended to the daily log.
// ---------------------------------------------------------------------------
Schedule::command('casey:sync-reference-data')
    ->everyFourHours()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/casey-reference-data-sync.log'));

// Full deep sync (municipalities, companies, members, policies) once daily.
Schedule::command('casey:sync-reference-data --include-members --include-policies')
    ->dailyAt('02:30')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/casey-reference-data-sync.log'));
