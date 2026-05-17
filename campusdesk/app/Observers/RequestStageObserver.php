<?php

namespace App\Observers;

use App\Models\RequestStage;

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
        if ($stage->isDirty('status')) {
            $stage->statusHistories()->create([
            'old_status' => $stage->getOriginal('status'),
            'new_status' => $stage->status,
            'changed_by' => $stage->handled_by,
            'request_id' => $stage->request_id,
            'request_stage_id' => $stage->id,
            'note' => $stage->staff_note ?? null,
           ]);
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
