<?php

namespace App\Services;

use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestStage;
use App\Models\User;
use Illuminate\Support\Str;

class RequestEventRecorder
{
    public function recordRequestCreated(DocumentRequest $request, int $firstDepartmentId): RequestEvent
    {
        $aggregateSequence = ((int) RequestEvent::query()
            ->where('request_id', $request->id)
            ->max('aggregate_sequence')) + 1;

        return RequestEvent::create([
            'id' => (string) Str::uuid(),
            'request_id' => $request->id,
            'event_name' => 'request.created',
            'schema_version' => 1,
            'aggregate_sequence' => $aggregateSequence,
            'delivery_status' => 'pending',
            'payload' => [
                'request_id' => $request->id,
            ],
            'audiences' => [
                'users' => [$request->student_id],
                'departments' => [$firstDepartmentId],
            ],
        ]);
    }

    public function recordRequestStatusChanged(DocumentRequest $request): RequestEvent
    {
        $aggregateSequence = ((int) RequestEvent::query()
            ->where('request_id', $request->id)
            ->max('aggregate_sequence')) + 1;

        $departmentIds = $request->requestStages()
            ->orderBy('sequence_order')
            ->pluck('department_id')
            ->unique()
            ->map(fn (mixed $departmentId): int => (int) $departmentId)
            ->values()
            ->all();

        return RequestEvent::create([
            'id' => (string) Str::uuid(),
            'request_id' => $request->id,
            'event_name' => 'request.status_changed',
            'schema_version' => 1,
            'aggregate_sequence' => $aggregateSequence,
            'delivery_status' => 'pending',
            'payload' => [
                'request_id' => $request->id,
            ],
            'audiences' => [
                'users' => [$request->student_id],
                'departments' => $departmentIds,
            ],
        ]);
    }

    public function recordRequestStageClaimed(
        DocumentRequest $request,
        RequestStage $stage,
        User $claimant,
    ): RequestEvent {
        $aggregateSequence = ((int) RequestEvent::query()
            ->where('request_id', $request->id)
            ->max('aggregate_sequence')) + 1;

        return RequestEvent::create([
            'id' => (string) Str::uuid(),
            'request_id' => $request->id,
            'event_name' => 'request_stage.claimed',
            'schema_version' => 1,
            'aggregate_sequence' => $aggregateSequence,
            'delivery_status' => 'pending',
            'payload' => [
                'request_id' => $request->id,
                'request_stage_id' => $stage->id,
            ],
            'audiences' => [
                'users' => [$request->student_id, $claimant->id],
                'departments' => [$stage->department_id],
            ],
        ]);
    }
}
