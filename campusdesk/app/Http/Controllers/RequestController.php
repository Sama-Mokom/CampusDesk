<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequestRequest;
use App\Http\Resources\RequestResource;
use App\Models\Request as UserRequest;
use App\Models\RequestType;
use App\Services\RequestCreationService;
use App\Services\RequestStatusNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public function store(StoreRequestRequest $request, RequestCreationService $requestCreation)
    {
        return DB::transaction(function () use ($request, $requestCreation) {
            $type = RequestType::findOrFail($request->request_type_id);
            $userRequest = $requestCreation->createForStudent(
                Auth::user(),
                $type,
                $request->description,
            );

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

    public function reopen(UserRequest $request, RequestStatusNotificationService $notifications)
    {
        $user = Auth::user();
        $isOwner = $request->student_id === $user->id;
        $isSuperAdmin = $user->role === 'staff'
            && $user->staffProfile?->admin_level === 'super_admin';

        abort_unless($isOwner || $isSuperAdmin, 403);

        return DB::transaction(function () use ($request, $user, $isSuperAdmin, $notifications) {
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

            $notifications->notifyStudent($lockedRequest, 'pending');

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

    public function collect(UserRequest $request)
    {
        $user = Auth::user();
        abort_unless($request->student_id === $user->id, 403);

        return DB::transaction(function () use ($request, $user) {
            $lockedRequest = UserRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->status === 'ready', 422, 'Only ready requests can be marked as collected.');

            $lockedRequest->update(['status' => 'collected']);
            $lockedRequest->statusHistories()->create([
                'old_status' => 'ready',
                'new_status' => 'collected',
                'changed_by' => $user->id,
                'request_stage_id' => null,
                'note' => 'Request marked as collected by student.',
            ]);

            return new RequestResource($lockedRequest->fresh()->load([
                'requestType', 'requestStages.department', 'attachments', 'statusHistories',
            ]));
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
