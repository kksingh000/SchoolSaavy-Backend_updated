<?php
/**
 * Convert a notification job to use the standardized pattern
 * 
 * This script helps convert a non-standardized notification job to 
 * follow the standard pattern defined in NOTIFICATION_JOBS_STANDARDIZATION.md
 * 
 * Usage: php convert_notification_job.php JobName
 * Example: php convert_notification_job.php SendPaymentConfirmJob
 */

if ($argc < 2) {
    echo "Usage: php convert_notification_job.php JobName\n";
    exit(1);
}

$jobName = $argv[1];
$jobFile = __DIR__ . "/app/Jobs/Notifications/{$jobName}.php";

if (!file_exists($jobFile)) {
    echo "Error: Job file not found at {$jobFile}\n";
    exit(1);
}

// Read the original file content
$originalContent = file_get_contents($jobFile);

// Create a backup of the original file
$backupFile = __DIR__ . "/app/Jobs/Notifications/{$jobName}.php.bak";
file_put_contents($backupFile, $originalContent);
echo "Backup created at {$backupFile}\n";

// Extract the namespace
preg_match('/namespace\s+(.+?);/', $originalContent, $namespaceMatches);
$namespace = $namespaceMatches[1] ?? 'App\\Jobs\\Notifications';

// Extract the job class content and properties
preg_match('/class\s+' . $jobName . '.*?\{(.*?)\}/s', $originalContent, $classMatches);
$classContent = $classMatches[1] ?? '';

// Extract constructor properties and parameters
preg_match('/public function __construct\((.*?)\)/s', $classContent, $constructorMatches);
$constructorParams = $constructorMatches[1] ?? '';

// Extract the handle method content
preg_match('/public function handle\(.*?\).*?\{(.*?)\}/s', $classContent, $handleMatches);
$handleContent = $handleMatches[1] ?? '';

// Extract notification data structure
preg_match('/\$notificationData\s*=\s*\[(.*?)\];/s', $handleContent, $notificationMatches);
$notificationData = $notificationMatches[1] ?? '';

// Extract job description from docblock if it exists
preg_match('/\/\*\*.*?Job:.*?(.*?)\*\//s', $originalContent, $docblockMatches);
$jobDescription = trim($docblockMatches[1] ?? '');

// Generate improved job template
$improvedTemplate = <<<EOD
<?php

namespace $namespace;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: $jobName
 * 
 * Sends notification to [recipients] about [purpose]
 * 
 * Notification Details:
 * - Type: [notification_type] (assignment, attendance, fee, etc.)
 * - Priority: [priority] (high, normal, low)
 * - Recipients: [recipient_type] (parent, teacher, admin)
 * - Action URL: [action_url_description]
 */
class $jobName implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int \$tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int \$backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        // Update these with your actual properties using constructor property promotion
        public int \$primaryId,
        public int \$schoolId,
        public int \$recipientId,
        public string \$title
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService \$notificationService): void
    {
        try {
            Log::info("Starting notification process", [
                'job' => class_basename(\$this),
                'primary_id' => \$this->primaryId,
            ]);

            // Build the notification message
            \$message = \$this->buildNotificationMessage();

            // Prepare notification data
            \$notificationData = [
                'school_id' => \$this->schoolId,
                'type' => 'notification_type',
                'title' => \$this->title,
                'message' => \$message,
                'priority' => 'normal',
                'target_type' => 'recipient_type',
                'target_ids' => [\$this->recipientId],
                'data' => [
                    'primary_id' => \$this->primaryId,
                    'action_url' => "/resource/{\$this->primaryId}",
                ],
            ];

            // Send notification
            \$result = \$notificationService->sendNotification(\$notificationData);

            Log::info("Notification sent successfully", [
                'notification_id' => \$result['notification_id'] ?? null,
                'recipient_id' => \$this->recipientId,
            ]);

        } catch (\Exception \$e) {
            Log::error("Failed to send notification", [
                'error' => \$e->getMessage(),
                'trace' => \$e->getTraceAsString(),
                'recipient_id' => \$this->recipientId,
            ]);

            throw \$e; // Re-throw for job retry
        }
    }

    /**
     * Build the notification message
     */
    private function buildNotificationMessage(): string
    {
        return "📝 This is a standardized notification message.";
    }
}
EOD;

// Write the improved template to a new file
$improvedFile = __DIR__ . "/app/Jobs/Notifications/{$jobName}_standardized.php";
file_put_contents($improvedFile, $improvedTemplate);

echo "Standard template created at {$improvedFile}\n";
echo "Next steps:\n";
echo "1. Open both files in your editor\n";
echo "2. Copy your specific properties and logic from the original to the standardized version\n";
echo "3. Test the new implementation\n";
echo "4. Once verified, rename {$jobName}_standardized.php to {$jobName}.php\n";