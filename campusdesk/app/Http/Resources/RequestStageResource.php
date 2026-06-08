<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestStageResource extends JsonResource
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
           'department_name' => $this->whenLoaded('department', fn() => $this->department->name),
           'sequence_order' => $this->sequence_order,
           'status' => $this->status,
           'handled_by' => $this->whenLoaded('handler', fn() => $this->handler?->name),
           'staff_note' => $this->staff_note,
           'updated_at' => $this->updated_at,
        ];
    }
}
