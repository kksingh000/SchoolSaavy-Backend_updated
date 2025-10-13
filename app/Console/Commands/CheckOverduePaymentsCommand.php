<?php

namespace App\Console\Commands;

use App\Events\FeeManagement\PaymentOverdue;
use App\Models\FeeInstallment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckOverduePaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:check-overdue 
                            {--min-days=1 : Minimum days overdue to trigger notification}
                            {--max-days=90 : Maximum days overdue to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue fee payments and send notifications to parents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minDays = (int) $this->option('min-days');
        $maxDays = (int) $this->option('max-days');
        $today = Carbon::today();

        $this->info("🔍 Checking for overdue payments (min: {$minDays} days, max: {$maxDays} days)");
        
        Log::info('📊 Starting overdue payments check', [
            'min_days' => $minDays,
            'max_days' => $maxDays,
            'started_at' => now()->toDateTimeString(),
        ]);

        $this->info('📚 Loading overdue installments...');

        // Get overdue installments
        $overdueInstallments = FeeInstallment::with(['studentFeePlan.student.parents.user'])
            ->where('status', 'pending')
            ->where('due_date', '<', $today)
            ->where('due_date', '>=', $today->copy()->subDays($maxDays))
            ->whereRaw('amount > COALESCE(paid_amount, 0)')
            ->get();

        $totalInstallments = $overdueInstallments->count();
        $this->info("💰 Found {$totalInstallments} overdue installments");

        if ($totalInstallments === 0) {
            $this->info('✅ No overdue payments found!');
            Log::info('✅ No overdue payments found');
            return Command::SUCCESS;
        }

        $notificationCount = 0;
        $progressBar = $this->output->createProgressBar($totalInstallments);
        $progressBar->start();

        foreach ($overdueInstallments as $installment) {
            $progressBar->advance();

            $student = $installment->studentFeePlan->student ?? null;
            
            if (!$student) {
                Log::warning('⚠️ Installment has no student', [
                    'installment_id' => $installment->id,
                    'student_fee_plan_id' => $installment->student_fee_plan_id,
                ]);
                continue;
            }

            // Calculate days overdue (absolute value since due_date is in the past)
            $daysOverdue = abs($today->diffInDays($installment->due_date, false));

            if ($daysOverdue < $minDays) {
                continue;
            }

            // Fire event for this overdue payment
            event(new PaymentOverdue($installment, $student, $daysOverdue));

            $notificationCount++;

            Log::info('⚠️ Overdue payment detected', [
                'installment_id' => $installment->id,
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'amount' => $installment->amount,
                'paid_amount' => $installment->paid_amount ?? 0,
                'remaining_amount' => $installment->amount - ($installment->paid_amount ?? 0),
                'due_date' => $installment->due_date->format('Y-m-d'),
                'days_overdue' => $daysOverdue,
            ]);
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Overdue Installments', $totalInstallments],
                ['Notifications Triggered', $notificationCount],
                ['Min Days Overdue', $minDays],
                ['Max Days Overdue', $maxDays],
                ['Check Date', $today->format('Y-m-d')],
            ]
        );

        $this->info('✅ Overdue payments check completed!');

        Log::info('✅ Overdue payments check completed successfully', [
            'total_overdue_installments' => $totalInstallments,
            'notifications_sent' => $notificationCount,
            'min_days' => $minDays,
            'max_days' => $maxDays,
            'completed_at' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }
}
