<?php

namespace App\Http\Requests;

use App\Models\RequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'student';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_type_id'      => ['required', 'exists:request_types,id'],
            'request_type_tier_id' => ['nullable', 'integer', 'exists:request_type_tiers,id'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'attachments'          => ['nullable', 'array'],
            'attachments.*'        => ['file', 'mimes:pdf,docx,jpg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $typeId = $this->input('request_type_id');
            if (! $typeId) {
                return;
            }

            $type = RequestType::with('activeTiers')->find($typeId);
            if (! $type) {
                return;
            }

            $tierId = $this->input('request_type_tier_id');
            $activeTiers = $type->activeTiers;

            if ($activeTiers->count() > 1) {
                if (! $tierId) {
                    $validator->errors()->add(
                        'request_type_tier_id',
                        'Please select a processing tier for this request type.'
                    );
                    return;
                }

                if (! $activeTiers->contains('id', (int) $tierId)) {
                    $validator->errors()->add(
                        'request_type_tier_id',
                        'The selected processing tier is invalid for this request type.'
                    );
                }
            } elseif ($activeTiers->count() === 1 && $tierId) {
                if (! $activeTiers->contains('id', (int) $tierId)) {
                    $validator->errors()->add(
                        'request_type_tier_id',
                        'The selected processing tier is invalid for this request type.'
                    );
                }
            }
        });
    }
}
