<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\SchoolSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishAssessmentResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $assessmentId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $assessmentId, int $userId)
    {
        $this->assessmentId = $assessmentId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the assessment
            $assessment = Assessment::find($this->assessmentId);
            
            if (!$assessment) {
                Log::error("Assessment not found for auto-publish: {$this->assessmentId}");
                return;
            }
            
            // Publish all unpublished results for this assessment
            $updated = AssessmentResult::where('assessment_id', $this->assessmentId)
                ->whereNull('result_published_at')
                ->update([
                    'result_published_at' => now(),
                    'published_by' => $this->userId
                ]);
            
            // Update assessment status
            $assessment->update(['status' => 'results_published']);
            
            // Send notifications if enabled
            $schoolId = $assessment->school_id;
            $sendNotifications = SchoolSetting::getSetting($schoolId, 'assessment_publish_notification', true);
            
            if ($sendNotifications) {
                // Dispatch notification job to send notifications to students and parents
                // You can implement this based on your notification system
                // NotifyAssessmentResultsPublished::dispatch($this->assessmentId);
            }
            
            Log::info("Auto-published {$updated} results for assessment {$this->assessmentId}");
        } catch (\Exception $e) {
            Log::error("Error in auto-publishing results for assessment {$this->assessmentId}: " . $e->getMessage());
        }
    }
}