<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAssessmentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assessment:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update assessment status based on date and time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting assessment status update...');
        
        $now = Carbon::now();
        $updatedCount = 0;

        try {
            // 1. Mark assessments as IN PROGRESS
            // Assessments that are scheduled and their start time has passed
            $assessmentsToStart = Assessment::where('status', 'scheduled')
                ->whereDate('assessment_date', '<=', $now->toDateString())
                ->get();

            foreach ($assessmentsToStart as $assessment) {
                try {
                    // Parse assessment date (extract date part only)
                    $assessmentDate = Carbon::parse($assessment->assessment_date)->startOfDay();
                    
                    // Parse start and end times (extract time part only)
                    $startTime = Carbon::parse($assessment->start_time);
                    $endTime = Carbon::parse($assessment->end_time);
                    
                    // Combine date with times
                    $startDateTime = $assessmentDate->copy()
                        ->setTime($startTime->hour, $startTime->minute, $startTime->second);
                    $endDateTime = $assessmentDate->copy()
                        ->setTime($endTime->hour, $endTime->minute, $endTime->second);
                    
                    // Check if start time has passed but end time hasn't
                    if ($now->gte($startDateTime) && $now->lt($endDateTime)) {
                        $assessment->update(['status' => 'in_progress']);
                        $updatedCount++;
                        $this->line("✓ Assessment #{$assessment->id} ({$assessment->title}) marked as IN PROGRESS");
                        
                        Log::info("Assessment #{$assessment->id} status changed: scheduled → in_progress");
                    }
                } catch (\Exception $e) {
                    $this->error("Error processing assessment #{$assessment->id}: " . $e->getMessage());
                    Log::error("Error processing assessment #{$assessment->id}: " . $e->getMessage());
                    continue;
                }
            }

            // 2. Mark assessments as COMPLETED
            // Assessments that are in_progress or scheduled and their end time has passed
            $assessmentsToComplete = Assessment::whereIn('status', ['scheduled', 'in_progress'])
                ->whereDate('assessment_date', '<=', $now->toDateString())
                ->get();

            foreach ($assessmentsToComplete as $assessment) {
                try {
                    // Parse assessment date (extract date part only)
                    $assessmentDate = Carbon::parse($assessment->assessment_date)->startOfDay();
                    
                    // Parse end time (extract time part only)
                    $endTime = Carbon::parse($assessment->end_time);
                    
                    // Combine date with end time
                    $endDateTime = $assessmentDate->copy()
                        ->setTime($endTime->hour, $endTime->minute, $endTime->second);
                    
                    // If end time has passed, mark as completed
                    if ($now->gte($endDateTime)) {
                        $assessment->update(['status' => 'completed']);
                        $updatedCount++;
                        $this->line("✓ Assessment #{$assessment->id} ({$assessment->title}) marked as COMPLETED");
                        
                        Log::info("Assessment #{$assessment->id} status changed → completed");
                    }
                } catch (\Exception $e) {
                    $this->error("Error processing assessment #{$assessment->id}: " . $e->getMessage());
                    Log::error("Error processing assessment #{$assessment->id}: " . $e->getMessage());
                    continue;
                }
            }

            $this->info("\n✓ Assessment status update completed!");
            $this->info("Total assessments updated: {$updatedCount}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error updating assessment status: ' . $e->getMessage());
            Log::error('Assessment status update failed: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
