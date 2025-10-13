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

/**
 * Check for overdue fee payments and send urgent notifications
 * Runs daily at 10:00 AM
 * Alerts parents about payments that are past due date
 * Min 1 day overdue, max 90 days lookback
 */
Schedule::command('fees:check-overdue --min-days=1 --max-days=90')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('ADMIN_EMAIL'))
    ->appendOutputTo(storage_path('logs/scheduled-fee-overdue.log'));

/**
 * Check for fee payments due tomorrow and send reminders
 * Runs daily at 6:00 PM
 * Evening reminder for payments due next day
 */
Schedule::command('fees:check-due-tomorrow')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('ADMIN_EMAIL'))
    ->appendOutputTo(storage_path('logs/scheduled-fee-reminder.log'));

// ============================================
// Attendance Scheduled Notifications
// ============================================

/**
 * Check for students with low attendance and send alerts
 * Runs weekly on Monday at 8:00 AM
 * Checks last 30 days attendance, alerts if below 75% threshold
 * Can be customized with --threshold and --days options
 */
Schedule::command('attendance:check-low --threshold=75 --days=30')
    ->weeklyOn(1, '08:00')  // Monday at 8 AM
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('ADMIN_EMAIL'))
    ->appendOutputTo(storage_path('logs/scheduled-low-attendance.log'));

