<?php

namespace App\Services;

use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestCreationService
{
    public function __construct(private readonly StageGenerationService $stages) {}

    public function createForStudent(User $student, RequestType $type, ?string $description = null): DocumentRequest
    {
        $profile = $student->studentProfile;

        if ($student->role !== 'student' || $profile === null) {
            throw new RuntimeException('Requests can only be created for users with a student profile.');
        }

        return DB::transaction(function () use ($student, $type, $description, $profile) {
            $request = $student->requests()->create([
                'request_type_id' => $type->id,
                'description' => $description,
                'status' => 'pending',
                'is_reopened' => false,
            ]);

            foreach ($this->stages->resolveSequence($type->default_department_sequence, $profile) as $index => $departmentId) {
                $request->requestStages()->create([
                    'department_id' => $departmentId,
                    'sequence_order' => $index + 1,
                    'status' => 'pending',
                ]);
            }

            $request->statusHistories()->create([
                'old_status' => null,
                'new_status' => 'pending',
                'changed_by' => null,
                'request_stage_id' => null,
                'note' => 'Request submitted by student.',
            ]);

            return $request;
        });
    }
}
