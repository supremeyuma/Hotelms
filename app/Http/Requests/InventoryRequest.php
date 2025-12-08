<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', \App\Models\InventoryItem::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'unique:inventory_items,sku,' . $this->inventory_item?->id],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'Quantity must be zero or greater.',
            'sku.unique' => 'SKU must be unique.',
        ];
    }
}
