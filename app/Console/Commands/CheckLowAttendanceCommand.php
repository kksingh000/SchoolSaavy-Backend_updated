<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Attendance;
use App\Events\Attendance\StudentLowAttendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckLowAttendanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:check-low
                          {--threshold=75 : Minimum attendance percentage threshold}
                          {--days=30 : Number of days to check (period)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for students with low attendance and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $days = (int) $this->option('days');
        
        $this->info("🔍 Checking for low attendance (threshold: {$threshold}%, period: last {$days} days)");
        Log::info('📊 Starting low attendance check', [
            'threshold' => $threshold,
            'days' => $days,
            'started_at' => now()->toDateTimeString()
        ]);

        try {
            $periodStart = Carbon::now()->subDays($days)->startOfDay();
            $periodEnd = Carbon::now()->endOfDay();

            // Get all active students with their attendance records
            $this->info('📚 Loading students and attendance data...');
            
            $students = Student::with(['parents.user', 'attendance' => function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('date', [$periodStart, $periodEnd]);
            }])
            ->where('is_active', true)
            ->get();

            $this->info("👥 Found {$students->count()} active students");

            $lowAttendanceCount = 0;
            $notificationsSent = 0;

            // Progress bar for better visibility
            $bar = $this->output->createProgressBar($students->count());
            $bar->start();

            foreach ($students as $student) {
                $attendanceRecords = $student->attendance;
                
                // Skip if no attendance records in the period
                if ($attendanceRecords->isEmpty()) {
                    $bar->advance();
                    continue;
                }

                // Calculate attendance statistics
                $totalDays = $attendanceRecords->count();
                $presentDays = $attendanceRecords->where('status', 'present')->count();
                $absentDays = $attendanceRecords->where('status', 'absent')->count();
                $attendancePercentage = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0;

                // Check if attendance is below threshold
                if ($attendancePercentage < $threshold) {
                    $lowAttendanceCount++;
                    
                    Log::info('⚠️ Low attendance detected', [
                        'student_id' => $student->id,
                        'student_name' => $student->first_name . ' ' . $student->last_name,
                        'attendance_percentage' => round($attendancePercentage, 2),
                        'total_days' => $totalDays,
                        'present_days' => $presentDays,
                        'absent_days' => $absentDays
                    ]);

                    // Fire event to send notifications
                    event(new StudentLowAttendance(
                        $student,
                        round($attendancePercentage, 2),
                        $totalDays,
                        $presentDays,
                        $absentDays,
                        $periodStart->toDateString(),
                        $periodEnd->toDateString()
                    ));

                    $notificationsSent++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Summary
            $this->info("✅ Low attendance check completed!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Students Checked', $students->count()],
                    ['Students with Low Attendance', $lowAttendanceCount],
                    ['Notifications Triggered', $notificationsSent],
                    ['Threshold Used', "{$threshold}%"],
                    ['Period', "{$days} days (from {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')})"],
                ]
            );

            Log::info('✅ Low attendance check completed successfully', [
                'total_students' => $students->count(),
                'low_attendance_count' => $lowAttendanceCount,
                'notifications_sent' => $notificationsSent,
                'threshold' => $threshold,
                'period_days' => $days,
                'completed_at' => now()->toDateTimeString()
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error during low attendance check: ' . $e->getMessage());
            Log::error('❌ Low attendance check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
