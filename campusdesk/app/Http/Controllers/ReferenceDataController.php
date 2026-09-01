<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\Department;
use App\Models\Programme;
use App\Models\RequestType; // Assuming your Model is named RequestType
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    /**
     * GET /api/faculties
     */
    public function faculties(): JsonResponse
    {
        $faculties = Faculty::all(['id', 'name', 'code', 'created_at']);
        return response()->json($faculties);
    }

    /**
     * GET /api/departments
     */
    public function departments(): JsonResponse
    {
        $departments = Department::all(['id', 'faculty_id', 'name', 'code', 'created_at']);
        return response()->json($departments);
    }

    /**
     * GET /api/programmes
     */
    public function programmes(): JsonResponse
    {
        $programmes = Programme::all(['id', 'faculty_id', 'name', 'code', 'degree_type']);
        return response()->json($programmes);
    }

    /**
     * GET /api/request-types
     */
    public function requestTypes(): JsonResponse
    {
        // If default_department_sequence is stored as JSON in your database,
        // make sure you cast it to an array in your RequestType Model!
        $requestTypes = RequestType::all(['id', 'name', 'description', 'default_department_sequence']);
        return response()->json($requestTypes);
    }
}