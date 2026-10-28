<?php

namespace App\Services;

use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestCreationService
{
    public function __construct(
        private readonly StageGenerationService $stages,
        private readonly RequestEventRecorder $events,
    ) {}

    public function createForStudent(
        User $student,
        RequestType $type,
        ?string $description = null,
        ?int $tierId = null,
    ): DocumentRequest {
        $profile = $student->studentProfile;

        if ($student->role !== 'student' || $profile === null) {
            throw new RuntimeException('Requests can only be created for users with a student profile.');
        }

        return DB::transaction(function () use ($student, $type, $description, $profile, $tierId) {
            $tier = null;
            if ($tierId) {
                $tier = $type->tiers()->where('id', $tierId)->first();
            }

            if (! $tier) {
                $tier = $type->activeTiers()->first();
            }

            if (! $tier) {
                $tier = $type->tiers()->create([
                    'code'            => 'standard',
                    'name'            => 'Standard',
                    'description'     => 'Standard processing service.',
                    'fee_amount'      => 2000,
                    'currency'        => 'XAF',
                    'turnaround_days' => 3,
                    'priority'        => 2,
                    'is_active'       => true,
                    'sort_order'      => 1,
                ]);
            }

            $departmentSequence = $this->stages->resolveSequence(
                $type->default_department_sequence,
                $profile,
            );

            $request = $student->requests()->create([
                'request_type_id'          => $type->id,
                'request_type_tier_id'     => $tier->id,
                'tier_name_snapshot'       => $tier->name,
                'fee_amount'               => $tier->fee_amount,
                'amount_paid'              => 0,
                'currency'                 => $tier->currency,
                'turnaround_days_snapshot' => $tier->turnaround_days,
                'tier_priority'            => $tier->priority,
                'paid_at'                  => null,
                'target_ready_at'          => null,
                'description'              => $description,
                'status'                   => DocumentRequest::STATUS_AWAITING_PAYMENT,
                'is_reopened'              => false,
            ]);

            foreach ($departmentSequence as $index => $departmentId) {
                $request->requestStages()->create([
                    'department_id'  => $departmentId,
                    'sequence_order' => $index + 1,
                    'status'         => 'pending',
                ]);
            }

            $request->statusHistories()->create([
                'old_status'       => null,
                'new_status'       => DocumentRequest::STATUS_AWAITING_PAYMENT,
                'changed_by'       => null,
                'request_stage_id' => null,
                'note'             => 'Request submitted by student. Awaiting processing fee payment.',
            ]);

            $this->events->recordRequestCreated($request, $departmentSequence[0]);

            return $request;
        });
    }
}
