<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\RequestType;
use App\Models\StatusHistory;
use App\Models\RequestStage;
use App\Models\Request as UserRequest;
use App\Http\Requests\StoreRequestRequest;

class RequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //Scope to the authenticated user only. Meaning only that users requests will be returned
        return Auth::user()->requests()->latest()->get();
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
    public function store(StoreRequestRequest $request)
    {
        
    // Database transaction is started here to ensure an all or nothing execution
    return DB::transaction(function () use ($request){
        $userRequest = Auth::user()->requests()->create([
            'request_type_id' => $request->request_type_id,
            'description' => $request->description,
            'status' => 'pending',
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
        // dump($type);
        
       
        //    foreach ($type->default_department_sequence as $index => $deptid){
        //     $stages = collect($type->default_department_sequence)->map(fn($deptid, $index) =>[
        //         'department_id' => $deptid,
        //         'sequence_order' => $index + 1,
        //         'status' => 'pending',
        //         'created_at' => now(),
        //         'updated_at' => now()
        //     ])->toArray();
        //     dd($stages);
        //     $userRequest->requestStages()->createMany($stages);
        //     dump($userRequest->requestStages);
        // }

        foreach ($type->default_department_sequence as $index => $deptid) {
        $userRequest->requestStages()->create([
        'department_id' => $deptid,
        'sequence_order' => $index + 1,
        'status' => 'pending',
    ]);
}
        $userRequest->statusHistory()->create([
            'new_status' => 'pending',
            'old_status' => null,
            'changed_by' => Auth::id(),
            'note' => 'Request submitted by student.',
        ]);

        return $userRequest->load(['requestStages', 'statusHistory']);
    });
    }

    /**
     * Display the specified resource.
     */
    public function show(UserRequest $request)
    {
        //
        if($request->student_id != Auth::id()){
            abort(403, 'Unauthorized action. ');
        }

        return $request->load(['stages', 'attachments', 'statusHistory']);
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
        // =
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
