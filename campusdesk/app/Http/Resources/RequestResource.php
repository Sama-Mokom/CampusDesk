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
         return [
           'id' => $this->id,
           'request_type' => $this->whenLoaded('requestType', fn() => $this->requestType->name),
           'request_type_tier_id' => $this->request_type_tier_id,
           'tier_name' => $this->tier_name_snapshot,
           'fee_amount' => $this->fee_amount,
           'amount_paid' => $this->amount_paid,
           'currency' => $this->currency,
           'turnaround_days' => $this->turnaround_days_snapshot,
           'tier_priority' => $this->tier_priority,
           'paid_at' => $this->paid_at,
           'target_ready_at' => $this->target_ready_at,
           'description' => $this->description,
           'status' => $this->status,
           'is_reopened' => $this->is_reopened,
           'created_at' => $this->created_at,
        //    'attachments' => $this->attachments,
           'stages' => RequestStageResource::collection($this->whenLoaded('requestStages')),
           'status_history' => $this->whenLoaded('statusHistories'),
           'attachments' => $this->whenLoaded('attachments', function () {
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
