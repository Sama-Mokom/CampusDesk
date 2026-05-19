<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Request as DocumentRequest;
use App\Mail\RequestStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendRequestStatusNotification implements ShouldQueue
{
    use Queueable;

    public User $user;
    public DocumentRequest $documentRequest;
    public string $newStatus;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, DocumentRequest $documentRequest, string $newStatus)
    {
        $this->user = $user;
        $this->documentRequest = $documentRequest->loadMissing('requestType');
        $this->newStatus = $newStatus;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user)->send(new RequestStatusUpdated($this->user, $this->documentRequest, $this->newStatus));
        //
    }
}
