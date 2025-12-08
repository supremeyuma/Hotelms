<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Payment::class);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'string', 'in:card,cash,transfer'],
            'status' => ['nullable', 'string', 'in:pending,successful,failed'],
            'transaction_ref' => ['nullable', 'string', 'unique:payments,transaction_ref,' . $this->payment?->id],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Payment amount must be at least 0.',
            'method.in' => 'Payment method must be card, cash, or transfer.',
        ];
    }
}
