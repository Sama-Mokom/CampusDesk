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
        $staffProfile = \App\Models\StaffProfile::where('user_id', $stage->handled_by)->value('id');
        if ($stage->isDirty('status')) {
            $stage->statusHistories()->create([
            'old_status' => $stage->getOriginal('status'),
            'new_status' => $stage->status,
            'changed_by' => $staffProfile,
            'request_id' => $stage->request_id,
            'request_stage_id' => $stage->id,
            'note' => $stage->staff_note ?? null,
           ]);
           $request = DocumentRequest::with('requestType')->find($stage->request_id);
           $user = User::find($request->student_id);

           SendRequestStatusNotification::dispatch($user, $request, $stage->status);
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
