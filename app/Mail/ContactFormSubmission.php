<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\ContactSubmission;

class ContactFormSubmission extends Mailable
{
    use Queueable, SerializesModels;

    public ContactSubmission $submission;

    /**
     * Create a new message instance.
     */
    public function __construct(ContactSubmission $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Contact Form Submission - SchoolSavvy',
            from: new \Illuminate\Mail\Mailables\Address('hello@schoolsaavy.com', 'SchoolSaavy'),
            replyTo: $this->submission->email
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.contact-form-submission',
            text: 'emails.contact-form-submission-text',
            with: [
                'submission' => $this->submission,
                'securityScore' => $this->submission->spam_score ?? [],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
