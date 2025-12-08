<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\MaintenanceTicket::class);
    }

    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'exists:rooms,id'],
            'staff_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title is required for maintenance ticket.',
            'status.in' => 'Status must be open, in_progress, resolved, or closed.',
        ];
    }
}
