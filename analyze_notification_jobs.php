<?php
/**
 * Notification Job Standardization Analyzer
 * 
 * This script analyzes all notification jobs in the SchoolSaavy system
 * and reports which ones need to be updated to meet the new standards.
 * 
 * Usage: php analyze_notification_jobs.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$jobsDirectory = __DIR__ . '/app/Jobs/Notifications';
$standardizedJobsFile = __DIR__ . '/standardized_jobs_report.md';

// Get list of all notification jobs
$jobs = glob($jobsDirectory . '/*.php');
$totalJobs = count($jobs);

$complianceResults = [];
$jobChecks = [];

// Define pattern requirements
$requirements = [
    'constructor_property_promotion' => 'Uses constructor property promotion',
    'dependency_injection' => 'Uses dependency injection for NotificationService',
    'handle_method_signature' => 'Correct handle method signature',
    'try_catch_block' => 'Has try-catch block with proper logging',
    'error_rethrow' => 'Re-throws exceptions for job retry',
    'notification_data_structure' => 'Follows standard notification data structure',
    'class_docblock' => 'Has proper class documentation',
    'builds_message' => 'Uses dedicated method to build message',
];

// Analyze each job file
foreach ($jobs as $jobFile) {
    $jobName = basename($jobFile, '.php');
    $content = file_get_contents($jobFile);
    
    $jobChecks[$jobName] = [
        'constructor_property_promotion' => preg_match('/public function __construct\(\s*public/', $content) === 1,
        'dependency_injection' => preg_match('/public function handle\(NotificationService \$notificationService\)/', $content) === 1,
        'handle_method_signature' => preg_match('/public function handle\(NotificationService \$notificationService\): void/', $content) === 1,
        'try_catch_block' => preg_match('/try\s*\{.*\}\s*catch\s*\(\\\Exception \$e\)\s*\{/s', $content) === 1,
        'error_rethrow' => preg_match('/throw \$e;/', $content) === 1,
        'notification_data_structure' => preg_match('/\$notificationData\s*=\s*\[.*\'school_id\'.*\'type\'.*\'title\'.*\'message\'.*\'priority\'.*\'target_type\'.*\'target_ids\'/s', $content) === 1,
        'class_docblock' => preg_match('/\/\*\*\s*\n\s*\* Job:.*\n\s*\*\s*\n\s*\* Sends notification/s', $content) === 1,
        'builds_message' => preg_match('/private function build\w+Message\(\)/', $content) === 1,
    ];
    
    $compliantCount = array_sum($jobChecks[$jobName]);
    $totalChecks = count($requirements);
    $complianceResults[$jobName] = [
        'score' => $compliantCount,
        'percentage' => round(($compliantCount / $totalChecks) * 100),
        'checks' => $jobChecks[$jobName]
    ];
}

// Sort results by compliance percentage
uasort($complianceResults, function($a, $b) {
    return $b['percentage'] - $a['percentage'];
});

// Generate report
$report = "# Notification Jobs Standardization Report\n\n";
$report .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
$report .= "## Overall Compliance\n\n";

$totalScore = 0;
foreach ($complianceResults as $job => $result) {
    $totalScore += $result['percentage'];
}
$averageCompliance = round($totalScore / count($complianceResults));

$report .= "- Total notification jobs: $totalJobs\n";
$report .= "- Average compliance: $averageCompliance%\n\n";

$report .= "## Jobs Requiring Updates\n\n";
$report .= "| Job | Compliance % | Issues |\n";
$report .= "|-----|-------------|--------|\n";

foreach ($complianceResults as $job => $result) {
    if ($result['percentage'] < 100) {
        $issues = [];
        foreach ($result['checks'] as $check => $passed) {
            if (!$passed) {
                $issues[] = $requirements[$check];
            }
        }
        $issuesList = implode(", ", $issues);
        $report .= "| $job | {$result['percentage']}% | $issuesList |\n";
    }
}

$report .= "\n## Fully Compliant Jobs\n\n";
$report .= "| Job | Compliance % |\n";
$report .= "|-----|-------------|\n";

foreach ($complianceResults as $job => $result) {
    if ($result['percentage'] === 100) {
        $report .= "| $job | 100% |\n";
    }
}

$report .= "\n## Detailed Requirements\n\n";
foreach ($requirements as $key => $desc) {
    $report .= "- **$desc**: ";
    
    $passCount = 0;
    foreach ($jobChecks as $job => $checks) {
        if ($checks[$key]) {
            $passCount++;
        }
    }
    
    $passPercentage = round(($passCount / $totalJobs) * 100);
    $report .= "$passCount/$totalJobs jobs ($passPercentage%)\n";
}

// Write report to file
file_put_contents($standardizedJobsFile, $report);

echo "Analysis complete. Report generated at: $standardizedJobsFile\n";