<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\RequestType;
use App\Models\Request as UserRequest;
use App\Http\Requests\StoreRequestRequest;
use App\Http\Resources\RequestResource;
use App\Services\StageGenerationService;
use UnexpectedValueException;


class RequestController extends Controller
{
    public function index()
    {
        return RequestResource::collection(
            Auth::user()->requests()->with(['requestType', 'attachments', 'requestStages.department', 'statusHistories'])->latest()->get()
        );
    }

    public function create()
    {
        //
    }

    /**
     * Create a request and its initial department stages.
     */
    public function store(StoreRequestRequest $request, StageGenerationService $stageGenerationService)
    {
        return DB::transaction(function () use ($request, $stageGenerationService) {
            $userRequest = Auth::user()->requests()->create([
                'request_type_id' => $request->request_type_id,
                'description' => $request->description,
                'status' => 'pending',
                'is_reopened' => false,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('attachments');
                    $userRequest->attachments()->create([
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            $type = RequestType::findOrFail($request->request_type_id);
            $studentProfile = Auth::user()->studentProfile;

            abort_if(
                is_null($studentProfile),
                403,
                'No student profile found for the authenticated user.'
            );

            $resolvedSequence = $stageGenerationService->resolveSequence(
                $type->default_department_sequence,
                $studentProfile
            );

            foreach ($resolvedSequence as $index => $deptId) {
                $userRequest->requestStages()->create([
                    'department_id' => $deptId,
                    'sequence_order' => $index + 1,
                    'status' => 'pending',
                ]);
            }

            $userRequest->statusHistories()->create([
                'new_status' => 'pending',
                'old_status' => null,
                'changed_by' => null,
                'note' => 'Request submitted by student.',
            ]);

            return new RequestResource(
                $userRequest->load(['requestType', 'requestStages.department', 'attachments', 'statusHistories'])
            );
        });
    }

    public function show(UserRequest $request)
    {
        $user = Auth::user();
        $isOwner = $request->student_id === $user->id;
        $isStaff = $user->role === 'staff';

        abort_unless($isOwner || $isStaff, 403);

        return new RequestResource(
            $request->load(['requestStages', 'attachments', 'statusHistories'])
        );
    }

    public function reopen(UserRequest $request)
    {
        $user = Auth::user();
        $isOwner = $request->student_id === $user->id;
        $isSuperAdmin = $user->role === 'staff'
            && $user->staffProfile?->admin_level === 'super_admin';

        abort_unless($isOwner || $isSuperAdmin, 403);

        return DB::transaction(function () use ($request, $user, $isSuperAdmin) {
            $lockedRequest = UserRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedRequest->status === 'rejected',
                422,
                'Only rejected requests can be reopened.'
            );

            $rejectedStages = $lockedRequest->requestStages()
                ->where('status', 'rejected')
                ->lockForUpdate()
                ->get();

            if ($rejectedStages->count() !== 1) {
                throw new UnexpectedValueException(
                    "Invariant violated: Expected exactly 1 rejected stage for request {$lockedRequest->id}, found {$rejectedStages->count()}."
                );
            }

            $rejectedStages->first()->update([
                'status' => 'pending',
                'handled_by' => null,
            ]);

            $lockedRequest->update([
                'status' => 'pending',
                'is_reopened' => true,
            ]);

            $lockedRequest->statusHistories()->create([
                'old_status' => 'rejected',
                'new_status' => 'pending',
                'changed_by' => $user->id,
                'note' => $isSuperAdmin
                    ? 'Request reopened by administrator.'
                    : 'Request reopened by student.',
            ]);

            return new RequestResource(
                $lockedRequest->fresh()->load([
                    'requestType',
                    'requestStages.department',
                    'attachments',
                    'statusHistories',
                ])
            );
        });
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
