<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Notification;
use App\Models\RequestStage;
use App\Models\StageReassignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeptAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $department = $this->primaryDepartment($request);
        $stages = $this->stageQuery($department->id)->get();
        $staff = $department->staffProfiles()->with('user:id,name')->get()
            ->map(fn ($profile) => ['id' => $profile->user_id, 'name' => $profile->user?->name, 'staff_id' => $profile->staff_id])
            ->values();

        return response()->json([
            'data' => $stages->map(fn (RequestStage $stage) => $this->stagePayload($stage)),
            'meta' => [
                'department' => ['id' => $department->id, 'name' => $department->name],
                'stats' => [
                    'total' => $stages->count(),
                    'unclaimed' => $stages->where('status', 'pending')->whereNull('handled_by')->count(),
                    'claimable' => $stages->filter(fn (RequestStage $stage) => $this->claimability($stage)['is_claimable'])->count(),
                    'blocked' => $stages->filter(fn (RequestStage $stage) => $stage->status === 'pending' && is_null($stage->handled_by) && ! $this->claimability($stage)['is_claimable'])->count(),
                    'in_review' => $stages->where('status', 'in_review')->count(),
                    'completed' => $stages->whereIn('status', ['approved', 'rejected'])->count(),
                ],
                'staff' => $staff,
            ],
        ]);
    }

    public function reassign(Request $request, RequestStage $stage): JsonResponse
    {
        $validated = $request->validate(['handled_by' => ['required', 'integer', 'exists:users,id']]);
        $department = $this->primaryDepartment($request);
        $actor = $request->user();

        $updated = DB::transaction(function () use ($stage, $validated, $department, $actor) {
            $locked = RequestStage::query()->lockForUpdate()->findOrFail($stage->id);
            abort_unless($locked->department_id === $department->id, 403);
            abort_unless($locked->status === 'in_review' && $locked->handled_by !== null, 422, 'Only claimed in-review stages can be reassigned.');
            abort_if($locked->handled_by === (int) $validated['handled_by'], 422, 'Choose a different staff member.');

            $recipient = User::query()->whereKey($validated['handled_by'])->where('role', 'staff')
                ->whereHas('staffProfile.departments', fn ($query) => $query->where('departments.id', $department->id))
                ->first();
            abort_unless($recipient, 422, 'The selected staff member is not assigned to this department.');

            $fromUserId = $locked->handled_by;
            $locked->update(['handled_by' => $recipient->id]);
            StageReassignment::create([
                'request_stage_id' => $locked->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $recipient->id,
                'reassigned_by' => $actor->id,
            ]);

            DB::afterCommit(function () use ($recipient, $locked, $actor) {
                $type = $locked->request()->with('requestType')->first()?->requestType?->name ?? 'document';
                Notification::create([
                    'user_id' => $recipient->id,
                    'type' => 'stage_reassigned',
                    'message' => "A {$type} request stage was reassigned to you by {$actor->name}.",
                ]);
            });

            return $this->stageQuery($department->id)->whereKey($locked->id)->firstOrFail();
        });

        return response()->json(['data' => $this->stagePayload($updated)]);
    }

    private function primaryDepartment(Request $request): Department
    {
        $profile = $request->user()->staffProfile;
        abort_unless($profile, 403);
        return $profile->departments()->wherePivot('is_primary', true)->first()
            ?? abort(422, 'Department admin has no primary department configured.');
    }

    private function stageQuery(int $departmentId)
    {
        return RequestStage::query()->where('department_id', $departmentId)->with([
            'department', 'user:id,name', 'request.requestType', 'request.student.studentProfile',
            'request.requestStages:id,request_id,sequence_order,status',
            'request.attachments', 'reassignments.fromUser:id,name', 'reassignments.toUser:id,name', 'reassignments.reassignedBy:id,name',
        ])->latest('updated_at');
    }

    private function stagePayload(RequestStage $stage): array
    {
        $claimability = $this->claimability($stage);

        return [
            'id' => $stage->id, 'request_id' => $stage->request_id, 'department_name' => $stage->department?->name,
            'sequence_order' => $stage->sequence_order, 'status' => $stage->status,
            'handled_by' => $stage->handled_by, 'handler' => $stage->handled_by ? ['id' => $stage->handled_by, 'name' => $stage->user?->name] : null,
            'staff_note' => $stage->staff_note, 'updated_at' => $stage->updated_at,
            'is_claimable' => $claimability['is_claimable'],
            'blocked_reason' => $claimability['blocked_reason'],
            'request' => ['id' => $stage->request?->id, 'description' => $stage->request?->description,
                'request_type' => $stage->request?->requestType?->name, 'created_at' => $stage->request?->created_at,
                'student_name' => $stage->request?->student?->name, 'student_matricule' => $stage->request?->student?->studentProfile?->matricule,
                'student_level' => $stage->request?->student?->studentProfile?->level,
                'attachments' => $stage->request?->attachments->map(fn ($file) => ['id' => $file->id, 'original_name' => $file->original_name, 'mime_type' => $file->mime_type]),],
            'reassignments' => $stage->reassignments->map(fn ($entry) => ['id' => $entry->id, 'from_user' => $entry->fromUser?->name, 'to_user' => $entry->toUser?->name, 'reassigned_by' => $entry->reassignedBy?->name, 'created_at' => $entry->created_at]),
        ];
    }

    /** @return array{is_claimable: bool, blocked_reason: ?string} */
    private function claimability(RequestStage $stage): array
    {
        if ($stage->status !== 'pending' || $stage->handled_by !== null) {
            return ['is_claimable' => false, 'blocked_reason' => null];
        }

        if ($stage->sequence_order === 1) {
            return ['is_claimable' => true, 'blocked_reason' => null];
        }

        $previous = $stage->request?->requestStages
            ->firstWhere('sequence_order', $stage->sequence_order - 1);

        if ($previous?->status === 'approved') {
            return ['is_claimable' => true, 'blocked_reason' => null];
        }

        if ($previous?->status === 'rejected') {
            return ['is_claimable' => false, 'blocked_reason' => "Stage {$previous->sequence_order} was rejected; the request must be reopened."];
        }

        return ['is_claimable' => false, 'blocked_reason' => "Waiting for stage " . ($stage->sequence_order - 1) . ' to be approved.'];
    }
}
