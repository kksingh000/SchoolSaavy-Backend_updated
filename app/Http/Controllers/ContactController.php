<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\ContactFormRequest;
use App\Models\ContactSubmission;
use App\Jobs\ProcessContactFormSubmission;

class ContactController extends Controller
{
    /**
     * Generate form token for bot protection
     */
    public function getFormToken(): JsonResponse
    {
        $timestamp = time();
        $token = md5(config('app.key') . $timestamp . request()->ip());

        // Store token in cache for 1 hour
        Cache::put("contact_token_{$token}", $timestamp, now()->addHour());

        return response()->json([
            'token' => $token,
            'timestamp' => $timestamp,
            'expires_at' => now()->addHour()->toISOString()
        ]);
    }

    /**
     * Submit contact form
     */
    public function submit(ContactFormRequest $request): JsonResponse
    {
        try {
            // Additional bot protection checks
            if (!$this->passesSecurityChecks($request)) {
                Log::warning('Contact form security check failed', [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'data' => $request->validated()
                ]);

                return response()->json([
                    'message' => 'Security validation failed. Please try again.',
                    'status' => 'error'
                ], 422);
            }

            // Create submission record
            $submission = ContactSubmission::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'school_name' => $request->school_name,
                'user_role' => $request->user_role,
                'total_students' => $request->total_students,
                'message' => $request->message,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => 'pending'
            ]);

            // Calculate and store bot protection score (for logging only)
            $securityScore = $this->calculateSecurityScore($request, $submission);
            $submission->update(['spam_score' => $securityScore]);

            // Disabled spam filtering - all submissions are treated as legitimate
            // You can manually review submissions in the admin panel
            Log::info('Contact form submission received', [
                'submission_id' => $submission->id,
                'security_score' => $securityScore,
                'ip' => request()->ip(),
                'email' => $submission->email
            ]);

            // Queue email processing to improve user experience
            ProcessContactFormSubmission::dispatch($submission);

            // Clean up used token
            Cache::forget("contact_token_{$request->form_token}");

            return response()->json([
                'message' => 'Thank you for your inquiry! We will contact you within 24 hours.',
                'submission_id' => $submission->id,
                'status' => 'success'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Contact form submission error', [
                'error' => $e->getMessage(),
                'ip' => request()->ip(),
                'data' => $request->validated()
            ]);

            return response()->json([
                'message' => 'An error occurred while processing your request. Please try again.',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Get contact submissions (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactSubmission::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('school_name', 'like', "%{$search}%");
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($submissions);
    }

    /**
     * Update submission status
     */
    public function updateStatus(Request $request, ContactSubmission $submission): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,contacted,demo_scheduled,converted,spam',
            'notes' => 'nullable|string|max:1000'
        ]);

        $submission->update([
            'status' => $request->status,
            'notes' => $request->notes,
            'contacted_at' => $request->status === 'contacted' ? now() : $submission->contacted_at
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'submission' => $submission
        ]);
    }

    /**
     * Additional security checks (simplified - only blocks obvious bots)
     */
    private function passesSecurityChecks(Request $request): bool
    {
        // Check if honeypot fields are filled (should be empty)
        if (!empty($request->website) || !empty($request->phone)) {
            return false;
        }

        // Very basic timing check (allow very fast submissions now)
        $timestamp = $request->timestamp;
        $timeTaken = time() - $timestamp;
        if ($timeTaken < 1) { // Reduced from 5 seconds to 1 second
            return false;
        }

        // Verify token exists in cache
        $token = $request->form_token;
        if (!Cache::has("contact_token_{$token}")) {
            return false;
        }

        return true;
    }

    /**
     * Calculate security score for bot detection
     */
    private function calculateSecurityScore(Request $request, ContactSubmission $submission): array
    {
        $score = 0;
        $flags = [];

        // Check user agent
        $userAgent = request()->userAgent();
        if (empty($userAgent)) {
            $score += 20;
            $flags[] = 'Missing user agent';
        } elseif (strlen($userAgent) < 20) {
            $score += 10; // Reduced from 15
            $flags[] = 'Short user agent';
        }

        // Check form completion time
        $timeTaken = time() - $request->timestamp;
        if ($timeTaken < 10) {
            $score += 25;
            $flags[] = 'Too fast completion';
        } elseif ($timeTaken > 3600) {
            $score += 10;
            $flags[] = 'Very slow completion';
        }

        // Check for suspicious email patterns (be less strict)
        $email = $request->email;
        if (preg_match('/^[a-z]+[0-9]+@[a-z]+\.(com|net|org)$/', $email)) {
            $score += 10; // Reduced from 15 and made pattern more specific
            $flags[] = 'Simple email pattern';
        }

        // Check school name patterns
        $schoolName = strtolower($request->school_name);
        $suspiciousWords = ['test', 'demo', 'sample', 'example', 'school', 'institute'];
        foreach ($suspiciousWords as $word) {
            if (strpos($schoolName, $word) !== false && strlen($schoolName) < 10) {
                $score += 20;
                $flags[] = 'Generic school name';
                break;
            }
        }

        // Check request headers (be more lenient)
        $requiredHeaders = ['Accept'];
        $missingHeaders = 0;
        foreach ($requiredHeaders as $header) {
            if (!request()->hasHeader($header)) {
                $missingHeaders++;
            }
        }
        if ($missingHeaders > 0) {
            $score += $missingHeaders * 5; // Reduced from 10
            $flags[] = "Missing {$missingHeaders} browser headers";
        }

        return [
            'total_score' => $score,
            'flags' => $flags,
            'risk_level' => $score > 60 ? 'high' : ($score > 30 ? 'medium' : 'low'), // Updated thresholds
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => $userAgent
        ];
    }
}
