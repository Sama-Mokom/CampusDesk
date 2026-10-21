<?php

namespace App\Services;

use App\Jobs\PublishRequestEvent;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RequestEventRecorder
{
    public function recordRequestCreated(DocumentRequest $request, int $firstDepartmentId): RequestEvent
    {
        return $this->recordAndDispatch($request, 'request.created', [
            'request_id' => $request->id,
        ], [
            'users' => [$request->student_id],
            'departments' => [$firstDepartmentId],
        ]);
    }

    public function recordRequestStatusChanged(DocumentRequest $request): RequestEvent
    {
        $departmentIds = $request->requestStages()
            ->orderBy('sequence_order')
            ->pluck('department_id')
            ->unique()
            ->map(fn (mixed $departmentId): int => (int) $departmentId)
            ->values()
            ->all();

        return $this->recordAndDispatch($request, 'request.status_changed', [
            'request_id' => $request->id,
        ], [
            'users' => [$request->student_id],
            'departments' => $departmentIds,
        ]);
    }

    public function recordRequestStageClaimed(
        DocumentRequest $request,
        RequestStage $stage,
        User $claimant,
    ): RequestEvent {
        return $this->recordAndDispatch($request, 'request_stage.claimed', [
            'request_id' => $request->id,
            'request_stage_id' => $stage->id,
        ], [
            'users' => [$request->student_id, $claimant->id],
            'departments' => [$stage->department_id],
        ]);
    }

    private function recordAndDispatch(
        DocumentRequest $request,
        string $eventName,
        array $payload,
        array $audiences,
    ): RequestEvent {
        $aggregateSequence = ((int) RequestEvent::query()
            ->where('request_id', $request->id)
            ->max('aggregate_sequence')) + 1;

        $event = RequestEvent::create([
            'id' => (string) Str::uuid(),
            'request_id' => $request->id,
            'event_name' => $eventName,
            'schema_version' => 1,
            'aggregate_sequence' => $aggregateSequence,
            'delivery_status' => 'pending',
            'payload' => $payload,
            'audiences' => $audiences,
        ]);

        DB::afterCommit(function () use ($event) {
            try {
                PublishRequestEvent::dispatch($event->id);
            } catch (Throwable $failure) {
                report($failure);
            }
        });

        return $event;
    }
}
