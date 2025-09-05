<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\User;
use App\Models\ClassRoom;
use App\Jobs\SendEventNotificationJob;
use App\Services\NotificationService;

class SetupEventNotificationDemo extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'demo:event-notifications {--school_id=1}';

    /**
     * The console command description.
     */
    protected $description = 'Set up a demo event notification system with sample data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schoolId = $this->option('school_id');

        try {
            $this->info("Setting up Event Notification Demo for School ID: {$schoolId}");
            $this->newLine();

            // Check if school exists
            $school = \App\Models\School::find($schoolId);
            if (!$school) {
                $this->error("School with ID {$schoolId} not found!");
                return Command::FAILURE;
            }

            $this->info("✓ School found: {$school->name}");

            // Check for users in the school
            $teachers = User::where('user_type', 'teacher')
                ->whereHas('teacher', function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->count();

            $parents = User::where('user_type', 'parent')
                ->whereHas('parent.students', function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->count();

            $this->info("✓ Found {$teachers} teachers and {$parents} parents");

            // Check for classes
            $classes = ClassRoom::where('school_id', $schoolId)->count();
            $this->info("✓ Found {$classes} classes");

            if ($teachers == 0 || $parents == 0 || $classes == 0) {
                $this->warn("Warning: Limited demo data available. Consider adding more users/classes.");
            }

            // Create a sample event
            if ($this->confirm('Create a sample event for testing?')) {
                $this->createSampleEvent($schoolId);
            }

            // Show queue status
            $this->newLine();
            $this->info("Queue Configuration:");
            $this->line("Default Queue: " . config('queue.default'));

            // Show next steps
            $this->newLine();
            $this->info("Next Steps:");
            $this->line("1. Start the queue worker: php artisan queue:work");
            $this->line("2. Create an event via the API or admin panel");
            $this->line("3. Check notification logs: tail -f storage/logs/laravel.log");
            $this->line("4. Test with: php artisan test:event-notification {event_id}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Demo setup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createSampleEvent($schoolId): void
    {
        $firstClass = ClassRoom::where('school_id', $schoolId)->first();
        $teacher = User::where('user_type', 'teacher')
            ->whereHas('teacher', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->first();

        if (!$teacher) {
            $this->warn("No teacher found to create event. Skipping sample event creation.");
            return;
        }

        $event = Event::create([
            'school_id' => $schoolId,
            'created_by' => $teacher->id,
            'title' => 'Demo School Event - Parent-Teacher Meeting',
            'description' => 'This is a demo event created to test the notification system. All parents and teachers are invited.',
            'type' => 'meeting',
            'priority' => 'medium',
            'event_date' => now()->addDays(3),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'location' => 'School Auditorium',
            'target_audience' => ['parents', 'teachers'],
            'affected_classes' => $firstClass ? [$firstClass->id] : [],
            'requires_acknowledgment' => true,
            'is_published' => true,
            'published_at' => now()
        ]);

        $this->info("✓ Sample event created: {$event->title} (ID: {$event->id})");
        $this->line("  Event Date: {$event->event_date->format('Y-m-d H:i')}");
        $this->line("  Priority: {$event->priority}");
        $this->line("  Target: " . implode(', ', $event->target_audience));

        $this->newLine();
        $this->info("The event notification should be automatically triggered!");
        $this->line("Test with: php artisan test:event-notification {$event->id}");
    }
}
