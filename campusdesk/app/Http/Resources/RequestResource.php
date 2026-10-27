<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestPayment = null;
        if ($this->relationLoaded('payments')) {
            $latest = $this->payments->sortByDesc('id')->first();
            $latestPayment = $latest ? new PaymentResource($latest) : null;
        }

        return [
            'id'                       => $this->id,
            'request_type_id'          => $this->request_type_id,
            'request_type'             => $this->whenLoaded('requestType', fn () => $this->requestType->name),
            'available_tiers'          => $this->whenLoaded('requestType', function () {
                return $this->requestType->relationLoaded('activeTiers')
                    ? $this->requestType->activeTiers->map(fn ($tier) => [
                        'id'              => $tier->id,
                        'request_type_id' => $tier->request_type_id,
                        'code'            => $tier->code,
                        'name'            => $tier->name,
                        'description'     => $tier->description,
                        'fee_amount'      => $tier->fee_amount,
                        'currency'        => $tier->currency,
                        'turnaround_days' => $tier->turnaround_days,
                        'priority'        => $tier->priority,
                        'is_active'       => $tier->is_active,
                    ])->values()->all()
                    : null;
            }),
            'request_type_tier_id'     => $this->request_type_tier_id,
            'pending_upgrade_tier_id' => $this->pending_upgrade_tier_id,
            'tier_name'                => $this->tier_name_snapshot,
            'fee_amount'               => $this->fee_amount,
            'amount_paid'              => $this->amount_paid,
            'amount_due'               => $this->amountDue(),
            'currency'                 => $this->currency,
            'turnaround_days'          => $this->turnaround_days_snapshot,
            'tier_priority'            => $this->tier_priority,
            'paid_at'                  => $this->paid_at,
            'target_ready_at'          => $this->target_ready_at,
            'description'              => $this->description,
            'status'                   => $this->status,
            'is_reopened'              => $this->is_reopened,
            'created_at'               => $this->created_at,
            'stages'                   => RequestStageResource::collection($this->whenLoaded('requestStages')),
            'status_history'           => $this->whenLoaded('statusHistories'),
            'payments'                 => PaymentResource::collection($this->whenLoaded('payments')),
            'latest_payment'           => $latestPayment,
            'pending_upgrade_tier'     => $this->whenLoaded('pendingUpgradeTier'),
            'attachments'              => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(fn ($file) => [
                    'id'            => $file->id,
                    'original_name' => $file->original_name,
                    'file_path'     => asset('storage/' . $file->file_path),
                    'mime_type'     => $file->mime_type,
                ]);
            }),
        ];
    }
}
