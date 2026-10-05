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
            'room_id' => ['nullable', 'exists:rooms,id'],
            'service_area' => ['required', 'string', 'in:kitchen,bar'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'in:postpaid,online'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_area.required' => 'Choose the service area for this order.',
            'items.*.item_name.required' => 'Each order item must have a name.',
            'items.*.qty.min' => 'Quantity must be at least 1.',
            'items.*.price.min' => 'Price must be at least 0.',
        ];
    }
}
