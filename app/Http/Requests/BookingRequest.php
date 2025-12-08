<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Users must be authorized via BookingPolicy
        return $this->user()->can('create', \App\Models\Booking::class);
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'booking_code' => ['nullable', 'string', 'unique:bookings,booking_code,' . $this->booking?->id],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
            'details' => ['nullable', 'array'],
            'details.*' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_out.after' => 'Check-out date must be after check-in date.',
            'room_id.exists' => 'Selected room does not exist.',
        ];
    }
}
