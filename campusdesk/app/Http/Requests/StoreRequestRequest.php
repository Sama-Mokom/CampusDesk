<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
        'request_type_id' => ['required', 'exists:request_types,id'],
        'description'     => ['nullable', 'string', 'max:1000'],
        'attachments'     => ['nullable', 'array'],
        'attachments.*'   => ['file', 'mimes:pdf,docx,jpg,png', 'max:5120'],
    ];
}
}
