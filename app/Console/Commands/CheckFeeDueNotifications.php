<?php

namespace App\Console\Commands;

use App\Events\FeeManagement\FeeInstallmentDue;
use App\Models\FeeInstallment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckFeeDueNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:check-due
                            {--days=3 : Number of days ahead to check for due fees}
                            {--school= : Specific school ID to check (optional)}
                            {--dry-run : Preview without sending notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for upcoming fee installments and send due notifications to parents';

    /**
     * Execute the console command.
     * Optimized with batch loading and minimal queries
     */
    public function handle()
    {
        $daysAhead = (int) $this->option('days');
        $schoolId = $this->option('school');
        $dryRun = $this->option('dry-run');

        $this->info("🔍 Checking fee installments due in next {$daysAhead} days...");
        
        $startTime = microtime(true);
        $checkDate = Carbon::today()->addDays($daysAhead);

        Log::info('📋 CheckFeeDueNotifications started', [
            'check_date' => $checkDate->format('Y-m-d'),
            'days_ahead' => $daysAhead,
            'school_id' => $schoolId,
            'dry_run' => $dryRun,
        ]);

        // Build optimized query with all necessary relationships
        $query = FeeInstallment::query()
            ->with([
                'component',
                'studentFeePlan.student.parents.user' // Eager load all needed relationships
            ])
            ->where('status', 'Pending')
            ->whereDate('due_date', '=', $checkDate)
            ->where(function($q) {
                $q->whereNull('paid_amount')
                  ->orWhereRaw('paid_amount < amount');
            });

        // Filter by school if specified
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        // Get installments in chunks for memory efficiency
        $totalInstallments = $query->count();
        
        if ($totalInstallments === 0) {
            $this->info("✅ No installments due on {$checkDate->format('Y-m-d')}");
            Log::info('✅ No installments found');
            return 0;
        }

        $this->info("📊 Found {$totalInstallments} installments due on {$checkDate->format('Y-m-d')}");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No notifications will be sent');
        }

        $notificationsSent = 0;
        $eventsQueued = 0;
        $studentsProcessed = [];
        $errors = [];

        // Process in chunks to avoid memory issues with large datasets
        $query->chunk(100, function ($installments) use (
            &$notificationsSent, 
            &$eventsQueued, 
            &$studentsProcessed, 
            &$errors,
            $dryRun
        ) {
            foreach ($installments as $installment) {
                try {
                    $student = $installment->studentFeePlan->student ?? null;

                    if (!$student) {
                        $errors[] = "No student found for installment {$installment->id}";
                        continue;
                    }

                    // Skip if already processed this student for this installment
                    $key = "{$student->id}_{$installment->id}";
                    if (in_array($key, $studentsProcessed)) {
                        continue;
                    }

                    $studentsProcessed[] = $key;

                    $parents = $student->parents;
                    
                    if ($parents->isEmpty()) {
                        $errors[] = "No parents found for student {$student->id}";
                        continue;
                    }

                    $remainingAmount = $installment->amount - ($installment->paid_amount ?? 0);
                    $componentName = $installment->component->name ?? 'Fee';

                    $this->line("  📝 {$student->first_name} {$student->last_name} - {$componentName}: ₹{$remainingAmount}");

                    if (!$dryRun) {
                        // Fire event - will be queued and processed asynchronously
                        event(new FeeInstallmentDue($installment, $student));
                        $eventsQueued++;
                        
                        // Count potential notifications (one per parent with user account)
                        $notificationsSent += $parents->filter(fn($p) => $p->user)->count();
                    }

                } catch (\Exception $e) {
                    $error = "Error processing installment {$installment->id}: {$e->getMessage()}";
                    $errors[] = $error;
                    Log::error('💥 CheckFeeDueNotifications error', [
                        'installment_id' => $installment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $executionTime = round(microtime(true) - $startTime, 2);

        // Display summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Installments', $totalInstallments],
                ['Students Processed', count($studentsProcessed)],
                ['Events Queued', $eventsQueued],
                ['Notifications (Expected)', $notificationsSent],
                ['Errors', count($errors)],
                ['Execution Time', $executionTime . 's'],
            ]
        );

        if (count($errors) > 0) {
            $this->newLine();
            $this->warn('⚠️ Errors encountered:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        Log::info('✅ CheckFeeDueNotifications completed', [
            'total_installments' => $totalInstallments,
            'students_processed' => count($studentsProcessed),
            'events_queued' => $eventsQueued,
            'notifications_expected' => $notificationsSent,
            'errors' => count($errors),
            'execution_time' => $executionTime,
        ]);

        if (!$dryRun && $eventsQueued > 0) {
            $this->info("✅ {$eventsQueued} events queued for processing. Check queue logs for delivery status.");
        }

        return 0;
    }
}
