<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RequestStage;
use Illuminate\Support\Facades\Auth;
use App\Models\Request as DocumentRequest;
use App\Http\Requests\UpdateStageStatusRequest as ResolveStageRequest;
use Illuminate\Support\Facades\DB;
use App\Models\StatusHistory as statusHistories;
use App\Http\Resources\RequestStageResource;
use App\Http\Resources\RequestResource;
use Illuminate\Http\JsonResponse;

class RequestStageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, DocumentRequest $docRequest = null)
{
   $user = $request->user();
   $deptIds = $user->staffProfile->departments->pluck('id');
    // IF fetching timeline/details for a specific request:
    if ($docRequest && $docRequest->exists) {
       // Get all pending unclaimed stages in staff's departments
    $candidates = RequestStage::whereIn('department_id', $deptIds)
        ->where('status', 'pending')
        ->whereNull('handled_by')
        ->with(['request.requestType', 'request.student.studentProfile', 
                'request.attachments', 'department'])
        ->get();

    // Filter: only show if first stage OR previous stage is approved
    $filtered = $candidates->filter(function ($stage) {
        if ($stage->sequence_order === 1) return true;

        return RequestStage::where('request_id', $stage->request_id)
            ->where('sequence_order', $stage->sequence_order - 1)
            ->where('status', 'approved')
            ->exists();
    });

    return RequestStageResource::collection($filtered->values());
    }

    // OTHERWISE: Fetch the staff department queue
    $staffProfile = $user->staffProfile ?? $user->staff_profile;
    
    if (!$staffProfile) {
        return response()->json(['message' => 'Staff profile not found.'], 403);
    }

    $departmentIds = $staffProfile->departments()->pluck('departments.id');

    $requestStages = RequestStage::query()
        ->whereIn('department_id', $departmentIds)
        ->where('status', 'pending')
        ->whereNull('handled_by')
        ->where(function ($query) {
            $query->where('sequence_order', 1)
                  ->orWhereExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('request_stages as prev')
                          ->whereColumn('prev.request_id', 'request_stages.request_id')
                          ->whereColumn('prev.sequence_order', DB::raw('request_stages.sequence_order - 1'))
                          ->where('prev.status', 'approved');
                  });
        })
        ->with([
            'request.requestType', 
            'request.student.studentProfile',
            'request.attachments',
            'department', 
            'handled_by'
        ])
        ->get();

    return RequestStageResource::collection($requestStages);
}

public function myCases(): JsonResponse
{
    $user = Auth::user();
    $stages = RequestStage::where('handled_by', $user->id)
        ->where('status', 'in_review')
        ->with(['request.requestType', 'request.student.studentProfile', 'request.attachments', 'department', 'user'])
        ->get();
    return response()->json(RequestStageResource::collection($stages));
}

public function forRequest(DocumentRequest $docRequest): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
{
    $stages = RequestStage::query()
        ->where('request_id', $docRequest->id)
        ->with([
            'request.requestType', 
            'request.student.studentProfile',
            'request.attachments',
            'department', 
            'handled_by'
        ])
        ->orderBy('sequence_order', 'asc')
        ->get();

    return RequestStageResource::collection($stages);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }
public function claim(Request $request, DocumentRequest $docRequest, RequestStage $stage)
{

    abort_if($stage->request_id != $docRequest->id, 404);

    $staff = $request->user()->staffProfile;
    $belongsToDept = $staff->departments->contains('id', $stage->department_id);
    abort_unless($belongsToDept, 403);

    DB::transaction(function () use ($request, $docRequest, $stage) {
        // Re-fetch the row with a pessimistic lock — serialises concurrent transactions
        $lockedStage = RequestStage::where('id', $stage->id)
            ->lockForUpdate()
            ->firstOrFail();

        // Sequential guard — now inside the lock (eliminates TOCTOU window)
        if ($lockedStage->sequence_order > 1) {
            $previousApproved = RequestStage::where('request_id', $lockedStage->request_id)
                ->where('sequence_order', $lockedStage->sequence_order - 1)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->count() > 0;

            abort_unless($previousApproved, 422, 'Previous stage must be approved first.');
        }

        // Atomic availability check — stage must still be pending and unclaimed
        abort_unless(
            $lockedStage->status === 'pending' && is_null($lockedStage->handled_by),
            409,
            'Stage already claimed.'
        );

        $lockedStage->update([
            'status'     => 'in_review',
            'handled_by' => $request->user()->id,
        ]);

        $staffProfileId = \App\Models\StaffProfile::where('user_id', Auth::id())->value('id');
        $docRequest->statusHistories()->create([
            'old_status'       => 'pending',
            'new_status'       => 'in_review',
            'changed_by'       => $staffProfileId,
            'request_stage_id' => $stage->id,
            'note'             => null,
        ]);

        $docRequest->update(['status' => 'in_review']);
    });

    return response()->json(['message' => 'Stage claimed'], 200);
}

public function resolve(ResolveStageRequest $formRequest, DocumentRequest $docRequest, RequestStage $stage)
{
    
      abort_if($stage->request_id !== $docRequest->id, 404);

      abort_unless($stage->handled_by === $formRequest->user()->id, 403);

      abort_unless($stage->status === 'in_review', 422);

      $status = $formRequest->validated()['status'];

      DB::transaction(function () use ($formRequest, $docRequest, $stage, $status){
        $stage->update([
            'status' => $status,
            'staff_note' => $formRequest->validated()['staff_note']?? null,
        ]);
        if ($status ==='approved') {
            $this->handleApproval($docRequest,$stage);
        } else {
            $docRequest->update(['status' =>'rejected']);
        }
      });

      return response()->json(['message'=> 'Stage resolved. '], 200);
}

private function handleApproval(DocumentRequest $docRequest, RequestStage $stage): void
{
    $isFinalStage = !RequestStage::where('request_id',$docRequest->id)
        ->where('sequence_order', '>', $stage->sequence_order)
        ->exists();
    $docRequest->update([
        'status' => $isFinalStage ? 'ready' : 'forwarded',
    ]);
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
