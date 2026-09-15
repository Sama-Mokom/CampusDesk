<?php

namespace App\Observers;

use App\Models\RequestStage;
use App\Jobs\SendRequestStatusNotification;
use App\Models\Request as DocumentRequest;
use Illuminate\Support\Facades\Auth;


class RequestStageObserver
{
    /**
     * Handle the RequestStage "created" event.
     */
    public function created(RequestStage $requestStage): void
    {
        //
    }

    /**
     * Handle the RequestStage "updated" event.
     */
   public function updated(RequestStage $stage): void
{
    if (!$stage->isDirty('status')) return;

    $stage->statusHistories()->create([
        'old_status'       => $stage->getOriginal('status'),
        'new_status'       => $stage->status,
        'changed_by'       => Auth::id() ?? $stage->handled_by,
        'request_id'       => $stage->request_id,
        'request_stage_id' => $stage->id,
        'note'             => $stage->staff_note ?? null,
    ]);

    // Only send notification on meaningful transitions
    if (in_array($stage->status, ['in_review', 'approved', 'rejected'])) {
        $request = DocumentRequest::with('requestType')->find($stage->request_id);
        if ($request) {
            $student = \App\Models\User::find($request->student_id);
            if ($student) {
                SendRequestStatusNotification::dispatch($student, $request, $stage->status);
            }
        }
    }
}

    /**
     * Handle the RequestStage "deleted" event.
     */
    public function deleted(RequestStage $requestStage): void
    {
        //
    }

    /**
     * Handle the RequestStage "restored" event.
     */
    public function restored(RequestStage $requestStage): void
    {
        //
    }

    /**
     * Handle the RequestStage "force deleted" event.
     */
    public function forceDeleted(RequestStage $requestStage): void
    {
        //
    }
}
