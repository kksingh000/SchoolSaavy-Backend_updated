<?php

namespace App\Console\Commands;

use App\Events\FeeManagement\PaymentDueTomorrow;
use App\Models\FeeInstallment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckPaymentsDueTomorrowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:check-due-tomorrow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for fee payments due tomorrow and send reminder notifications to parents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = Carbon::tomorrow();

        $this->info("🔍 Checking for payments due tomorrow ({$tomorrow->format('Y-m-d')})");
        
        Log::info('📊 Starting payments due tomorrow check', [
            'tomorrow_date' => $tomorrow->format('Y-m-d'),
            'started_at' => now()->toDateTimeString(),
        ]);

        $this->info('📚 Loading installments due tomorrow...');

        // Get installments due tomorrow that are not fully paid
        $dueTomorrowInstallments = FeeInstallment::with(['studentFeePlan.student.parents.user'])
            ->where('status', 'pending')
            ->whereDate('due_date', $tomorrow)
            ->whereRaw('amount > COALESCE(paid_amount, 0)')
            ->get();

        $totalInstallments = $dueTomorrowInstallments->count();
        $this->info("💰 Found {$totalInstallments} payments due tomorrow");

        if ($totalInstallments === 0) {
            $this->info('✅ No payments due tomorrow!');
            Log::info('✅ No payments due tomorrow');
            return Command::SUCCESS;
        }

        $notificationCount = 0;
        $progressBar = $this->output->createProgressBar($totalInstallments);
        $progressBar->start();

        foreach ($dueTomorrowInstallments as $installment) {
            $progressBar->advance();

            $student = $installment->studentFeePlan->student ?? null;
            
            if (!$student) {
                Log::warning('⚠️ Installment has no student', [
                    'installment_id' => $installment->id,
                ]);
                continue;
            }

            // Fire event for this payment reminder
            event(new PaymentDueTomorrow($installment, $student));

            $notificationCount++;

            Log::info('⏰ Payment due tomorrow', [
                'installment_id' => $installment->id,
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'amount' => $installment->amount,
                'paid_amount' => $installment->paid_amount ?? 0,
                'remaining_amount' => $installment->amount - ($installment->paid_amount ?? 0),
                'due_date' => $installment->due_date->format('Y-m-d'),
            ]);
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->table(
            ['Metric', 'Value'],
            [
                ['Payments Due Tomorrow', $totalInstallments],
                ['Reminders Sent', $notificationCount],
                ['Due Date', $tomorrow->format('Y-m-d')],
                ['Check Date', now()->format('Y-m-d')],
            ]
        );

        $this->info('✅ Payment reminders sent successfully!');

        Log::info('✅ Payments due tomorrow check completed successfully', [
            'total_due_tomorrow' => $totalInstallments,
            'reminders_sent' => $notificationCount,
            'due_date' => $tomorrow->format('Y-m-d'),
            'completed_at' => now()->toDateTimeString(),
        ]);

        return Command::SUCCESS;
    }
}
