<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestType;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    /**
     * GET /api/faculties
     */
    public function faculties(): JsonResponse
    {
        $faculties = Faculty::all(['id', 'name', 'code', 'matricule_prefix', 'created_at']);
        return response()->json($faculties);
    }

    /**
     * GET /api/departments
     */
    public function departments(): JsonResponse
    {
        $departments = Department::all(['id', 'faculty_id', 'name', 'code', 'type', 'created_at']);
        return response()->json($departments);
    }

    /**
     * GET /api/programmes
     */
    public function programmes(): JsonResponse
    {
        $programmes = Programme::all(['id', 'faculty_id', 'department_id', 'name', 'code', 'degree_type']);
        return response()->json($programmes);
    }

    /**
     * GET /api/request-types
     */
    public function requestTypes(): JsonResponse
    {
        $requestTypes = RequestType::with(['activeTiers'])->get()->map(function (RequestType $type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'default_department_sequence' => $type->default_department_sequence,
                'tiers' => $type->activeTiers->map(fn ($tier) => [
                    'id'              => $tier->id,
                    'code'            => $tier->code,
                    'name'            => $tier->name,
                    'description'     => $tier->description,
                    'fee_amount'      => $tier->fee_amount,
                    'currency'        => $tier->currency,
                    'turnaround_days' => $tier->turnaround_days,
                    'priority'        => $tier->priority,
                    'is_active'       => $tier->is_active,
                    'sort_order'      => $tier->sort_order,
                ])->values(),
            ];
        });

        return response()->json($requestTypes);
    }
}
