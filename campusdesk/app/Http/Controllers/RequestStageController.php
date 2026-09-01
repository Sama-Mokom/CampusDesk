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

    // IF fetching timeline/details for a specific request:
    if ($docRequest && $docRequest->exists) {
        $stages = RequestStage::query()
            ->where('request_id', $docRequest->id)
            ->with([
                'request.requestType', 
                'request.student.studentProfile',
                'request.attachments', // Eager-load attachments for the details modal
                'department', 
                'handled_by',
            ])
            ->orderBy('sequence_order', 'asc')
            ->get();

        return RequestStageResource::collection($stages);
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

$claimed = RequestStage::where('id', $stage->id)
->where('status', 'pending')
->whereNull('handled_by')
->update([
    'status' => 'in_review',
    'handled_by' => $request->user()->id,
]);

abort_if($claimed === 0, 409, 'Stage already claimed. ');

$docRequest->update(['status' => 'in_review']);

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
