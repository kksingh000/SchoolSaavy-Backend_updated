<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic assessment status updates
Schedule::command('assessment:update-status')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// ============================================
// Fee Management Scheduled Notifications
// ============================================

/**
 * Check for fee installments due in 3 days and send reminders
 * Runs daily at 9:00 AM
 * Sends notifications to parents about upcoming due fees
 */
Schedule::command('fees:check-due --days=3')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('ADMIN_EMAIL'))
    ->appendOutputTo(storage_path('logs/scheduled-fee-due.log'));

/**
 * Check for fee installments due tomorrow (urgent reminder)
 * Runs daily at 5:00 PM
 * More urgent reminder as deadline approaches
 */
Schedule::command('fees:check-due --days=1')
    ->dailyAt('17:00')
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('ADMIN_EMAIL'))
    ->appendOutputTo(storage_path('logs/scheduled-fee-due.log'));

/**
 * Check for fee installments due today (last chance reminder)
 * Runs daily at 8:00 AM
 * Final reminder on the due date
 */
Schedule::command('fees:check-due --days=0')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('ADMIN_EMAIL'))
    ->appendOutputTo(storage_path('logs/scheduled-fee-due.log'));

