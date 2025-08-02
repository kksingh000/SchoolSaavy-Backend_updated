<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\ClassRoom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    /**
     * Seed attendance data for a specific date range
     */
    public function run()
    {
        $this->command->info('📊 Creating additional attendance records...');

        // Get Cambridge International School ID
        $schoolId = 7;

        // Create attendance for the last 30 days (weekdays only)
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $classes = ClassRoom::where('school_id', $schoolId)->get();

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            // Skip weekends
            if ($currentDate->isWeekend()) {
                $currentDate->addDay();
                continue;
            }

            foreach ($classes as $class) {
                // Get students in this class
                $studentIds = DB::table('class_student')
                    ->where('class_id', $class->id)
                    ->where('is_active', true)
                    ->pluck('student_id');

                foreach ($studentIds as $studentId) {
                    // Check if attendance already exists for this date
                    $existingAttendance = Attendance::where([
                        'class_id' => $class->id,
                        'student_id' => $studentId,
                        'date' => $currentDate->toDateString()
                    ])->first();

                    if (!$existingAttendance) {
                        // 85% attendance rate with some variation
                        $isPresent = rand(1, 100) <= 85;
                        $status = $isPresent ? 'present' : (rand(1, 100) <= 15 ? 'late' : 'absent');

                        Attendance::create([
                            'school_id' => $schoolId,
                            'class_id' => $class->id,
                            'student_id' => $studentId,
                            'date' => $currentDate->toDateString(),
                            'status' => $status,
                            'check_in_time' => $status === 'present' ? '08:00:00' : ($status === 'late' ? '08:' . rand(15, 30) . ':00' : null),
                            'check_out_time' => $status !== 'absent' ? '15:00:00' : null,
                            'marked_by' => $class->class_teacher_id ? $class->classTeacher->user_id : 1,
                        ]);
                    }
                }
            }

            $currentDate->addDay();
        }

        $this->command->info('✅ Additional attendance records created successfully!');
    }
}
