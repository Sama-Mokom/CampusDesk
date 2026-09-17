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
use Illuminate\Support\Str;

class RequestStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public DocumentRequest $documentRequest;
    public string $newStatus;
    public string $statusLabel;
    public string $statusMessage;
    public string $nextStep;
    public string $statusColor;
    public string $statusBackground;
    public string $requestReference;
    public string $dashboardUrl;
    public ?string $staffNote;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, DocumentRequest $documentRequest, string $newStatus)
    {
        $this->user = $user;
        $this->documentRequest = $documentRequest->loadMissing([
            'requestType',
            'requestStages.department',
        ]);
        $this->newStatus = $newStatus;
        $this->requestReference = 'CD-' . str_pad((string) $this->documentRequest->id, 6, '0', STR_PAD_LEFT);
        $this->dashboardUrl = rtrim((string) config('app.frontend_url'), '/') . '/student';

        $status = $this->statusDetails($newStatus);
        $this->statusLabel = $status['label'];
        $this->statusMessage = $status['message'];
        $this->nextStep = $status['next_step'];
        $this->statusColor = $status['color'];
        $this->statusBackground = $status['background'];
        $this->staffNote = $this->latestStaffNote();
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

    /** @return array{label: string, message: string, next_step: string, color: string, background: string} */
    private function statusDetails(string $status): array
    {
        return match ($status) {
            'in_review' => ['label' => 'In review', 'message' => 'A member of staff has started reviewing your request.', 'next_step' => 'No action is needed right now. We will email you when there is another update.', 'color' => '#2563eb', 'background' => '#dbeafe'],
            'approved' => ['label' => 'Stage approved', 'message' => 'A processing stage for your request has been approved.', 'next_step' => 'Your request will continue through the remaining review steps. We will let you know when it is ready for collection.', 'color' => '#047857', 'background' => '#d1fae5'],
            'rejected' => ['label' => 'Action needed', 'message' => 'Your request could not be approved at this stage.', 'next_step' => 'Review the note below, then open CampusDesk to check your request and reopen it if appropriate.', 'color' => '#b91c1c', 'background' => '#fee2e2'],
            'ready' => ['label' => 'Ready for collection', 'message' => 'Your document is ready to be collected.', 'next_step' => 'Open CampusDesk for the collection details before visiting the relevant office.', 'color' => '#0f766e', 'background' => '#ccfbf1'],
            default => ['label' => Str::of($status)->replace('_', ' ')->title()->toString(), 'message' => 'There has been an update to your request.', 'next_step' => 'Open CampusDesk to view the latest details.', 'color' => '#475569', 'background' => '#e2e8f0'],
        };
    }

    private function latestStaffNote(): ?string
    {
        if ($this->newStatus !== 'rejected') {
            return null;
        }

        return $this->documentRequest->requestStages
            ->where('status', $this->newStatus)
            ->sortByDesc('updated_at')
            ->pluck('staff_note')
            ->filter(fn (?string $note) => filled($note))
            ->first();
    }
}
