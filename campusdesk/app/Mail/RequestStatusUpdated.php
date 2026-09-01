<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Request as DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public DocumentRequest $documentRequest;
    public string $newStatus;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, DocumentRequest $documentRequest, string $newStatus)
    {
        $this->user = $user;
        $this->documentRequest = $documentRequest->loadMissing('requestType');
        $this->newStatus = $newStatus;
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your ' . $this->documentRequest->requestType->name . ' request',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.request-status-update',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
