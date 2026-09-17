<?php

namespace App\Services;

use App\Models\Request as DocumentRequest;
use App\Models\RequestStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Creates valid request lifecycle fixtures without dispatching notifications. */
class SeedRequestProgressionService
{
    public function progress(DocumentRequest $request, string $target): void
    {
        DB::transaction(function () use ($request, $target) {
            $request = $request->fresh(['requestStages']) ?? throw new RuntimeException('Request no longer exists.');
            $first = $request->requestStages->sortBy('sequence_order')->firstOrFail();

            match ($target) {
                'pending' => null,
                'in_review' => $this->claim($request, $first),
                'forwarded' => $this->approveThrough($request, 1),
                'ready' => $this->approveThrough($request, $request->requestStages->count()),
                'rejected' => $this->reject($request, $first),
                'collected' => $this->collect($request),
                default => throw new RuntimeException("Unsupported seeded request target '{$target}'."),
            };
        });
    }

    private function claim(DocumentRequest $request, RequestStage $stage): User
    {
        $staff = $this->staffFor($stage);
        $this->transitionStage($stage, 'in_review', $staff);
        $this->transitionRequest($request, 'in_review', $staff->id);

        return $staff;
    }

    private function approveThrough(DocumentRequest $request, int $count): void
    {
        $stages = $request->requestStages()->orderBy('sequence_order')->get();
        if ($count < 1 || $count > $stages->count()) {
            throw new RuntimeException('Seeded approval count is outside the stage sequence.');
        }

        foreach ($stages->take($count) as $stage) {
            $staff = $this->claim($request, $stage);
            $isFinal = $stage->sequence_order === $stages->last()->sequence_order;
            $this->transitionStage($stage, 'approved', $staff, 'Approved by seeded workflow.');
            $this->transitionRequest($request, $isFinal ? 'ready' : 'forwarded', $staff->id);
        }
    }

    private function reject(DocumentRequest $request, RequestStage $stage): void
    {
        $staff = $this->claim($request, $stage);
        $this->transitionStage($stage, 'rejected', $staff, 'Rejected by seeded workflow.');
        $this->transitionRequest($request, 'rejected', $staff->id, 'Rejected by seeded workflow.');
    }

    private function collect(DocumentRequest $request): void
    {
        $this->approveThrough($request, $request->requestStages()->count());
        $this->transitionRequest($request, 'collected', $request->student_id, 'Request collected by student.');
    }

    private function staffFor(RequestStage $stage): User
    {
        return User::query()->where('role', 'staff')
            ->whereHas('staffProfile.departments', fn ($query) => $query->whereKey($stage->department_id))
            ->orderBy('id')->first()
            ?? throw new RuntimeException("No seeded staff member belongs to department {$stage->department_id}.");
    }

    private function transitionStage(RequestStage $stage, string $status, User $staff, ?string $note = null): void
    {
        $stage->update(['status' => $status, 'handled_by' => $staff->id, 'staff_note' => $note]);
    }

    private function transitionRequest(DocumentRequest $request, string $status, int $actorId, ?string $note = null): void
    {
        $oldStatus = $request->status;
        $request->update(['status' => $status]);
        $request->statusHistories()->create([
            'old_status' => $oldStatus, 'new_status' => $status, 'changed_by' => $actorId,
            'request_stage_id' => null, 'note' => $note,
        ]);
    }
}
