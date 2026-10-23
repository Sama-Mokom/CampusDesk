<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
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

            $transcript = RequestType::updateOrCreate(
                ['name' => 'Transcript Request'],
                [
                    'description'                  => 'Official academic transcript.',
                    'default_department_sequence'  => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $trdId],
                ]
            );

            $transcriptTiers = [
                [
                    'code'            => 'normal',
                    'name'            => 'Normal Mode',
                    'description'     => 'Standard processing time for official transcripts.',
                    'fee_amount'      => 1000,
                    'currency'        => 'XAF',
                    'turnaround_days' => 14,
                    'priority'        => 1,
                    'is_active'       => true,
                    'sort_order'      => 1,
                ],
                [
                    'code'            => 'fast',
                    'name'            => 'Fast Mode',
                    'description'     => 'Expedited transcript processing.',
                    'fee_amount'      => 2500,
                    'currency'        => 'XAF',
                    'turnaround_days' => 7,
                    'priority'        => 2,
                    'is_active'       => true,
                    'sort_order'      => 2,
                ],
                [
                    'code'            => 'super_fast',
                    'name'            => 'Super Fast Mode',
                    'description'     => 'Urgent transcript issuance with highest queue priority.',
                    'fee_amount'      => 5000,
                    'currency'        => 'XAF',
                    'turnaround_days' => 3,
                    'priority'        => 3,
                    'is_active'       => true,
                    'sort_order'      => 3,
                ],
            ];

            foreach ($transcriptTiers as $tierData) {
                RequestTypeTier::updateOrCreate(
                    [
                        'request_type_id' => $transcript->id,
                        'code'            => $tierData['code'],
                    ],
                    $tierData
                );
            }

            $standardTypes = [
                [
                    'name'        => 'Attestation of Enrollment',
                    'description' => 'Official attestation confirming current enrollment.',
                    'sequence'    => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $aoeId],
                ],
                [
                    'name'        => 'Attestation of Completion of Degree',
                    'description' => 'Official attestation confirming completion of degree.',
                    'sequence'    => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $aocId],
                ],
                [
                    'name'        => 'Correction of Transcript',
                    'description' => 'Request to correct errors on an issued transcript.',
                    'sequence'    => ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $trdId],
                ],
            ];

            foreach ($standardTypes as $typeDef) {
                $type = RequestType::updateOrCreate(
                    ['name' => $typeDef['name']],
                    [
                        'description'                 => $typeDef['description'],
                        'default_department_sequence' => $typeDef['sequence'],
                    ]
                );

                RequestTypeTier::updateOrCreate(
                    [
                        'request_type_id' => $type->id,
                        'code'            => 'standard',
                    ],
                    [
                        'name'            => 'Standard',
                        'description'     => 'Standard processing service.',
                        'fee_amount'      => 2000,
                        'currency'        => 'XAF',
                        'turnaround_days' => 3,
                        'priority'        => 2,
                        'is_active'       => true,
                        'sort_order'      => 1,
                    ]
                );
            }
        });
    }
}
