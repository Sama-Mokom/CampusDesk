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
        'id'             => $this->id,
        'request_id'     => $this->request_id,
        'department_name' => $this->whenLoaded('department', fn() => $this->department->name),
        'sequence_order' => $this->sequence_order,
        'status'         => $this->status,
        'handled_by'     => $this->whenLoaded('handler', fn() => $this->handler?->name),
        'staff_note'     => $this->staff_note,
        'updated_at'     => $this->updated_at,
        'request'        => $this->whenLoaded('request', fn() => [
            'id'           => $this->request->id,
            'description'  => $this->request->description,
            'request_type' => $this->request->requestType?->name,
            'created_at'   => $this->request->created_at,
            'student_name' => $this->request->student?->name,
            'student_matricule' => $this->request->student?->studentProfile?->matricule,
            'student_level'     => $this->request->student?->studentProfile?->level,
        ]),
    ];
}
}
