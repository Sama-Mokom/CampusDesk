<?php

namespace App\Observers;

use App\Models\RequestStage;
use App\Models\User;
use App\Jobs\SendRequestStatusNotification;
use App\Models\Request as DocumentRequest;


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

    // Resolve staff_profile id from handled_by (user_id)
    $staffProfileId = null;
    if ($stage->handled_by) {
        $staffProfileId = \App\Models\StaffProfile::where('user_id', $stage->handled_by)
            ->value('id');
    }

    $stage->statusHistories()->create([
        'old_status'       => $stage->getOriginal('status'),
        'new_status'       => $stage->status,
        'changed_by'       => $staffProfileId,
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
