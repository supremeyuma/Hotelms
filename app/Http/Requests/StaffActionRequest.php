<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Must be a staff member
        return $this->user()->staffProfile !== null;
    }

    public function rules(): array
    {
        return [
            'action_code' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'action_code.required' => 'Action code is required for this operation.',
            'action_code.min' => 'Action code must be at least 6 characters.',
        ];
    }
}
