<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class TestStudentParents extends Command
{
    protected $signature = 'test:student-parents {student_id?}';
    protected $description = 'Test student-parent relationship for debugging notifications';

    public function handle()
    {
        $studentId = $this->argument('student_id');

        if ($studentId) {
            $student = Student::with('parents.user')->find($studentId);
        } else {
            $student = Student::with('parents.user')->first();
        }

        if (!$student) {
            $this->error('No student found!');
            return 1;
        }

        $this->info("Student: {$student->first_name} {$student->last_name} (ID: {$student->id})");
        $this->info("School ID: {$student->school_id}");
        $this->line('');

        $parents = $student->parents;
        $this->info("Parents count: {$parents->count()}");
        $this->line('');

        if ($parents->isEmpty()) {
            $this->warn('⚠️ This student has NO parents linked!');
            $this->line('');
            $this->info('To link a parent, use the admin panel or run:');
            $this->line('php artisan tinker');
            $this->line('>>> $student = Student::find(' . $student->id . ');');
            $this->line('>>> $parent = App\Models\Parents::first();');
            $this->line('>>> $student->parents()->attach($parent->id, [\'relationship\' => \'father\', \'is_primary\' => true]);');
            return 1;
        }

        foreach ($parents as $index => $parent) {
            $this->line("Parent #" . ($index + 1) . ":");
            $this->line("  - Parent ID: {$parent->id}");
            $this->line("  - User ID: {$parent->user_id}");
            
            if ($parent->user) {
                $this->info("  ✅ User found: {$parent->user->name} ({$parent->user->email})");
                $this->line("     User Type: {$parent->user->user_type}");
                $this->line("     Is Active: " . ($parent->user->is_active ? 'Yes' : 'No'));
            } else {
                $this->error("  ❌ No user account linked to this parent!");
            }
            
            $this->line("  - Relationship: {$parent->pivot->relationship}");
            $this->line("  - Is Primary: " . ($parent->pivot->is_primary ? 'Yes' : 'No'));
            $this->line('');
        }

        $this->info('✅ Student has ' . $parents->count() . ' parent(s) with user accounts');
        return 0;
    }
}
