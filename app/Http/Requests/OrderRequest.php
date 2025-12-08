<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Order::class);
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'order_code' => ['nullable', 'string', 'unique:orders,order_code,' . $this->order?->id],
            'total' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:pending,completed,cancelled'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.item_name.required' => 'Each order item must have a name.',
            'items.*.qty.min' => 'Quantity must be at least 1.',
            'items.*.price.min' => 'Price must be at least 0.',
        ];
    }
}
