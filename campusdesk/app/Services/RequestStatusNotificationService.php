<?php

namespace App\Services;

use App\Jobs\SendRequestStatusNotification;
use App\Models\Notification;
use App\Models\Request as DocumentRequest;

class RequestStatusNotificationService
{
    public function notifyStudent(DocumentRequest $request, string $status): void
    {
        $message = $this->messageFor($request, $status);

        Notification::create([
            'user_id' => $request->student_id,
            'type'    => 'request_status_updated',
            'message' => $message,
        ]);

        $request->loadMissing(['student', 'requestType']);
        $student = $request->student;
        if ($student) {
            SendRequestStatusNotification::dispatch($student, $request, $status)
                ->afterCommit();
        }
    }

    private function messageFor(DocumentRequest $request, string $status): string
    {
        $name = $request->requestType?->name ?? 'document';

        return match ($status) {
            'awaiting_payment' => "Your {$name} request has been submitted. Please complete fee payment to proceed.",
            'in_review'        => "Your {$name} request is now under review.",
            'forwarded'        => "Your {$name} request has moved to the next processing stage.",
            'ready'            => "Your {$name} request is ready for collection.",
            'rejected'         => "Your {$name} request needs attention. Review the staff note.",
            'pending'          => $request->is_reopened
                ? "Your {$name} request has been reopened and returned to the queue."
                : "Payment confirmed. Your {$name} request has entered the processing queue.",
            default            => "Your {$name} request has been updated.",
        };
    }
}
