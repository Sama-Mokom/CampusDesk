<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RequestType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Resolve the literal IDs for the three Records Office sub-departments
            // by code — never hard-code guessed IDs; they're only known after
            // DepartmentSeeder has run.
            $trdId = Department::where('code', 'TRD')->value('id')
                ?? throw new RuntimeException(
                    "Department 'TRD' not found. Run DepartmentSeeder before RequestTypeSeeder."
                );

            $aoeId = Department::where('code', 'AOE')->value('id')
                ?? throw new RuntimeException(
                    "Department 'AOE' not found. Run DepartmentSeeder before RequestTypeSeeder."
                );

            $aocId = Department::where('code', 'AOC')->value('id')
                ?? throw new RuntimeException(
                    "Department 'AOC' not found. Run DepartmentSeeder before RequestTypeSeeder."
                );

            // default_department_sequence is a template that mixes symbolic placeholders
            // (resolved per-request by StageGenerationService, since they depend on the
            // specific requesting student) with literal IDs (the only genuinely static
            // values — the final-stage Records Office sub-department).
            //
            // Format: ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', <literal_id>]

            RequestType::updateOrCreate(
                ['name' => 'Transcript Request'],
                [
                    'description'                  => 'Official academic transcript.',
                    'default_department_sequence'  => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $trdId],
                ]
            );

            RequestType::updateOrCreate(
                ['name' => 'Attestation of Enrollment'],
                [
                    'description'                  => 'Official attestation confirming current enrollment.',
                    'default_department_sequence'  => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $aoeId],
                ]
            );

            RequestType::updateOrCreate(
                ['name' => 'Attestation of Completion of Degree'],
                [
                    'description'                  => 'Official attestation confirming completion of degree.',
                    'default_department_sequence'  => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $aocId],
                ]
            );

            RequestType::updateOrCreate(
                ['name' => 'Correction of Transcript'],
                [
                    'description'                  => 'Request to correct errors on an issued transcript.',
                    'default_department_sequence'  => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $trdId],
                ]
            );
        });
    }
}
