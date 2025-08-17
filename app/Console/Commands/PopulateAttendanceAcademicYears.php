<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\AcademicYear;
use Carbon\Carbon;

class PopulateAttendanceAcademicYears extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:populate-academic-years';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate academic_year_id for existing attendance records based on date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting to populate academic year IDs for attendance records...');

        // Get all attendance records without academic year
        $attendances = Attendance::whereNull('academic_year_id')->get();
        $totalRecords = $attendances->count();

        if ($totalRecords === 0) {
            $this->info('✅ No attendance records need updating.');
            return;
        }

        $this->info("📊 Found {$totalRecords} attendance records to update.");

        $progress = $this->output->createProgressBar($totalRecords);
        $updated = 0;
        $failed = 0;

        foreach ($attendances as $attendance) {
            try {
                // Find the academic year this attendance date falls into
                $academicYear = AcademicYear::where('school_id', $attendance->school_id)
                    ->where('start_date', '<=', $attendance->date)
                    ->where('end_date', '>=', $attendance->date)
                    ->first();

                if ($academicYear) {
                    $attendance->update(['academic_year_id' => $academicYear->id]);
                    $updated++;
                } else {
                    // If no exact match, try to find the closest academic year
                    $closestYear = AcademicYear::where('school_id', $attendance->school_id)
                        ->orderByRaw('ABS(DATEDIFF(?, start_date))', [$attendance->date])
                        ->first();

                    if ($closestYear) {
                        $attendance->update(['academic_year_id' => $closestYear->id]);
                        $updated++;
                    } else {
                        $failed++;
                        $this->warn("⚠️  No academic year found for attendance on {$attendance->date} (School ID: {$attendance->school_id})");
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("❌ Failed to update attendance ID {$attendance->id}: " . $e->getMessage());
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();

        $this->info("✅ Successfully updated {$updated} attendance records");
        if ($failed > 0) {
            $this->warn("⚠️  Failed to update {$failed} records");
        }

        $this->info('🎉 Attendance academic year population completed!');
    }
}
