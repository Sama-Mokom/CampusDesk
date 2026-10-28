<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'request_id'              => $this->request_id,
            'request_type_tier_id'    => $this->request_type_tier_id,
            'external_id'             => $this->external_id,
            'provider'                => $this->provider,
            'provider_transaction_id' => $this->provider_transaction_id,
            'payment_type'            => $this->payment_type,
            'checkout_mode'           => $this->checkout_mode,
            'payment_method'          => $this->payment_method,
            'payer_phone'             => $this->payer_phone,
            'amount'                  => $this->amount,
            'currency'                => $this->currency,
            'status'                  => $this->status,
            'checkout_url'            => $this->checkout_url,
            'failure_reason'          => $this->failure_reason,
            'initiated_at'            => $this->initiated_at?->toIso8601String(),
            'completed_at'            => $this->completed_at?->toIso8601String(),
            'expires_at'              => $this->expires_at?->toIso8601String(),
        ];
    }
}
