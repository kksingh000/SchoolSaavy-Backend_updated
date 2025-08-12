<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ContactSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'school_name',
        'user_role',
        'total_students',
        'message',
        'ip_address',
        'user_agent',
        'status',
        'contacted_at',
        'notes',
        'spam_score'
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'spam_score' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // User roles enum
    public const USER_ROLES = [
        'Teacher',
        'Principal',
        'Administrator',
        'IT Manager',
        'Other'
    ];

    // Status enum
    public const STATUSES = [
        'pending' => 'Pending Review',
        'contacted' => 'Contacted',
        'demo_scheduled' => 'Demo Scheduled',
        'converted' => 'Converted to Customer',
        'spam' => 'Marked as Spam'
    ];

    /**
     * Scope to get non-spam submissions
     */
    public function scopeNotSpam($query)
    {
        return $query->where('status', '!=', 'spam');
    }

    /**
     * Scope to get pending submissions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Mark as contacted
     */
    public function markAsContacted($notes = null)
    {
        $this->update([
            'status' => 'contacted',
            'contacted_at' => Carbon::now(),
            'notes' => $notes
        ]);
    }

    /**
     * Mark as spam
     */
    public function markAsSpam($reason = null)
    {
        $spamScore = $this->spam_score ?? [];
        $spamScore['manual_review'] = [
            'marked_at' => Carbon::now(),
            'reason' => $reason
        ];

        $this->update([
            'status' => 'spam',
            'spam_score' => $spamScore
        ]);
    }

    /**
     * Calculate spam score based on various factors
     */
    public function calculateSpamScore()
    {
        $score = 0;
        $reasons = [];

        // Check for suspicious patterns
        $email = strtolower($this->email);
        $name = strtolower($this->full_name);
        $school = strtolower($this->school_name);

        // Email patterns
        if (preg_match('/^[a-z0-9]+@[a-z0-9]+\.[a-z]{2,}$/', $email)) {
            $score += 10;
            $reasons[] = 'Simple email pattern';
        }

        // Suspicious domains
        $suspiciousDomains = ['tempmail', 'guerrillamail', '10minutemail', 'mailinator'];
        foreach ($suspiciousDomains as $domain) {
            if (strpos($email, $domain) !== false) {
                $score += 50;
                $reasons[] = 'Temporary email service';
                break;
            }
        }

        // Name patterns
        if (preg_match('/^[a-z]+[0-9]+$/', $name)) {
            $score += 20;
            $reasons[] = 'Name with numbers pattern';
        }

        // School name patterns
        if (strlen($school) < 3) {
            $score += 15;
            $reasons[] = 'Very short school name';
        }

        // Generic school names
        $genericNames = ['test', 'demo', 'sample', 'example'];
        foreach ($genericNames as $generic) {
            if (strpos($school, $generic) !== false) {
                $score += 25;
                $reasons[] = 'Generic school name';
                break;
            }
        }

        return [
            'score' => $score,
            'reasons' => $reasons,
            'risk_level' => $score > 40 ? 'high' : ($score > 20 ? 'medium' : 'low')
        ];
    }
}
