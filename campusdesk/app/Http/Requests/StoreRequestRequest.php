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
        if (Gate::allows('is-student')) {
            return true;
    }
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required|integer|max:10',
            'request_type_id' => 'required|integer|max:10',
            'description' => 'string|max:255',
            'status' => 'rule::enum(status)',
            'is_reopened' => 'boolean',
            'created_at' => 'required|date',
            'attachments' => 'file|mimes:pdf,docx|max:5120'
            //
        ];
    }
}
