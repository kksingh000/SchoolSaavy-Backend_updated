<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ContactSubmission;

class ContactFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\.\-\']+$/', // Only letters, spaces, dots, hyphens, apostrophes
            ],
            'email' => [
                'required',
                'email:rfc', // Remove DNS validation for easier testing
                'max:255',
                // Remove the overly strict regex that blocks personal emails
            ],
            'school_name' => [
                'required',
                'string',
                'min:3',
                'max:200',
                // Make generic name detection less strict - only exact matches
                'not_regex:/^(test|demo|sample|example)$/i',
            ],
            'user_role' => [
                'required',
                Rule::in(ContactSubmission::USER_ROLES),
            ],
            'total_students' => [
                'nullable',
                'string',
                'max:50',
            ],
            'message' => [
                'nullable',
                'string',
                'max:1000',
            ],
            // Honeypot fields (should be empty)
            'website' => 'nullable|max:0', // Honeypot field
            'phone' => 'nullable|max:0',   // Honeypot field

            // Bot protection
            'timestamp' => 'required|integer|min:' . (time() - 3600), // Form must be submitted within 1 hour
            'form_token' => 'required|string|size:32', // Custom form token
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'full_name.regex' => 'Full name contains invalid characters.',
            'email.email' => 'Please provide a valid email address.',
            'school_name.not_regex' => 'Please provide a valid school/institution name.',
            'school_name.min' => 'School name must be at least 3 characters.',
            'website.max' => 'Invalid submission detected.',
            'phone.max' => 'Invalid submission detected.',
            'timestamp.min' => 'Form submission timeout. Please refresh and try again.',
            'form_token.required' => 'Invalid form submission.',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize data
        $this->merge([
            'full_name' => $this->sanitizeInput($this->input('full_name')),
            'email' => strtolower(trim($this->input('email'))),
            'school_name' => $this->sanitizeInput($this->input('school_name')),
            'message' => $this->sanitizeInput($this->input('message')),
        ]);
    }

    /**
     * Sanitize input to prevent XSS and normalize text
     */
    private function sanitizeInput($input): string
    {
        if (!$input) return '';

        // Remove extra whitespace and normalize
        $input = trim(preg_replace('/\s+/', ' ', $input));

        // Remove potentially dangerous characters
        $input = preg_replace('/[<>"\']/', '', $input);

        return $input;
    }

    /**
     * Additional validation after basic rules
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Only check essential security measures
            $this->validateRateLimit($validator);
            $this->validateFormToken($validator);
            // Removed bot detection - let all user agents through
        });
    }

    /**
     * Validate that submission is not from a bot
     */
    private function validateNotBot($validator): void
    {
        $userAgent = request()->userAgent();
        $ip = request()->ip();

        // Check for obvious bot user agents (be less aggressive)
        $botPatterns = [
            'curl',
            'wget',
            'python-requests',
            'scrapy',
            'spider'
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $validator->errors()->add('bot_detected', 'Automated submissions are not allowed.');
                return;
            }
        }

        // Only check if user agent is completely missing (allow short ones)
        if (empty($userAgent)) {
            $validator->errors()->add('invalid_browser', 'Invalid browser detected.');
            return;
        }

        // Don't require all headers for testing - just check Accept  
        if (!request()->hasHeader('Accept')) {
            $validator->errors()->add('missing_headers', 'Invalid request headers.');
            return;
        }
    }

    /**
     * Validate rate limiting (more generous limits)
     */
    private function validateRateLimit($validator): void
    {
        $ip = request()->ip();
        $email = $this->input('email');

        // Check IP-based rate limiting (20 submissions per hour - increased from 5)
        $ipSubmissions = ContactSubmission::where('ip_address', $ip)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($ipSubmissions >= 20) {
            $validator->errors()->add('rate_limit', 'Too many submissions from this location. Please try again later.');
            return;
        }

        // Check email-based rate limiting (3 submissions per day - increased from 1)
        if ($email) {
            $emailSubmissions = ContactSubmission::where('email', $email)
                ->where('created_at', '>', now()->subDay())
                ->count();

            if ($emailSubmissions >= 3) {
                $validator->errors()->add('duplicate_email', 'Multiple submissions with this email received today.');
                return;
            }
        }
    }

    /**
     * Validate custom form token
     */
    private function validateFormToken($validator): void
    {
        $token = $this->input('form_token');
        $timestamp = $this->input('timestamp');

        if (!$token || !$timestamp) {
            $validator->errors()->add('invalid_token', 'Invalid form submission.');
            return;
        }

        // Validate token format (should be a hash)
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $validator->errors()->add('invalid_token', 'Invalid form token format.');
            return;
        }

        // In a real implementation, you might want to verify the token against a cache/database
        // For now, we'll just check if it's properly formatted
    }
}
