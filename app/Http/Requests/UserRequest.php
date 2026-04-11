<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', \App\Models\User::class);
    }

    public function rules(): array
    {
        $userId = $this->user?->id ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $userId],
            'password' => $userId ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'],
            'role' => ['nullable', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email address is already taken.',
            'password.min' => 'Password must be at least 6 characters.',
        ];
    }
}
