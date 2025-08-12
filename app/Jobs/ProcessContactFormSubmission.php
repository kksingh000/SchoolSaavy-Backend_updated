<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\ContactSubmission;
use App\Mail\ContactFormSubmission;
use App\Mail\ContactFormAutoReply;

class ProcessContactFormSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ContactSubmission $submission;

    /**
     * Create a new job instance.
     */
    public function __construct(ContactSubmission $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing contact form submission via queue', [
                'submission_id' => $this->submission->id,
                'email' => $this->submission->email
            ]);

            // Send notification emails to both admin addresses
            Mail::to('hello@schoolsaavy.com')->send(new ContactFormSubmission($this->submission));
            Mail::to('sunnybhadana44@gmail.com')->send(new ContactFormSubmission($this->submission));

            // Send auto-reply to user
            Mail::to($this->submission->email)->send(new ContactFormAutoReply($this->submission));

            Log::info('Contact form emails sent successfully via queue', [
                'submission_id' => $this->submission->id,
                'recipient_email' => $this->submission->email,
                'admin_emails' => ['hello@schoolsaavy.com', 'sunnybhadana44@gmail.com']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send contact form emails via queue', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage()
            ]);

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Contact form submission job failed', [
            'submission_id' => $this->submission->id,
            'error' => $exception->getMessage()
        ]);
    }
}
