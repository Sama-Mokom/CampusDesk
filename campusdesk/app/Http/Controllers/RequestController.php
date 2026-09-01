<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Department;
use App\Models\RequestType;
use App\Models\StatusHistory as statusHistories;
use App\Models\RequestStage;
use App\Models\Request as UserRequest;
use App\Http\Requests\StoreRequestRequest;
use App\Http\Resources\RequestStageResource;
use App\Http\Resources\RequestResource;


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
     * Resolve symbolic department tokens in a sequence to real department IDs.
     *
     * Tokens:
     *   "STUDENT_DEPARTMENT" → the student's own department_id
     *   "FACULTY_RECORDS"    → the records-type department in the student's faculty
     *   <integer>            → used as-is
     */
    private function resolveSequence(array $sequence, \App\Models\StudentProfile $profile): array
    {
        return array_map(function ($entry) use ($profile) {

            if ($entry === 'STUDENT_DEPARTMENT') {
                return $profile->department_id;
            }

            if ($entry === 'FACULTY_RECORDS') {
                $dept = Department::where('faculty_id', $profile->faculty_id)
                    ->where('type', 'records')
                    ->first();

                abort_if(
                    is_null($dept),
                    422,
                    "No records department found for faculty ID {$profile->faculty_id}. " .
                    "Please contact an administrator to set one up."
                );

                return $dept->id;
            }

            // Already a real department ID
            return (int) $entry;

        }, $sequence);
    }

    public function store(StoreRequestRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $userRequest = Auth::user()->requests()->create([
                'request_type_id' => $request->request_type_id,
                'description'     => $request->description,
                'status'          => 'pending',
                'is_reopened'     => false,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('attachments');
                    $userRequest->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getMimeType(),
                        'file_size'     => $file->getSize(),
                    ]);
                }
            }

            $type           = RequestType::findOrFail($request->request_type_id);
            $studentProfile = Auth::user()->studentProfile;

            abort_if(
                is_null($studentProfile),
                403,
                'No student profile found for the authenticated user.'
            );

            $resolvedSequence = $this->resolveSequence($type->default_department_sequence, $studentProfile);

            foreach ($resolvedSequence as $index => $deptId) {
                $userRequest->requestStages()->create([
                    'department_id'  => $deptId,
                    'sequence_order' => $index + 1,
                    'status'         => 'pending',
                ]);
            }

            $userRequest->statusHistories()->create([
                'new_status' => 'pending',
                'old_status' => null,
                'changed_by' => null,
                'note'       => 'Request submitted by student.',
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
