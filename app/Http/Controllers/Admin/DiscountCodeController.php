<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiscountCodeController extends Controller
{
    public function index()
    {
        $codes = DiscountCode::query()
            ->with('creator:id,name')
            ->withCount([
                'redemptions as active_redemptions_count' => fn ($query) => $query
                    ->whereIn('status', ['reserved', 'applied'])
                    ->whereNull('released_at'),
            ])
            ->latest()
            ->get()
            ->map(function (DiscountCode $code) {
                return [
                    'id' => $code->id,
                    'name' => $code->name,
                    'code' => $code->code,
                    'description' => $code->description,
                    'applies_to' => $code->applies_to,
                    'applies_to_label' => DiscountCode::scopeOptions()[$code->applies_to] ?? $code->applies_to,
                    'discount_type' => $code->discount_type,
                    'discount_type_label' => DiscountCode::discountTypeOptions()[$code->discount_type] ?? $code->discount_type,
                    'discount_value' => (float) $code->discount_value,
                    'valid_from' => optional($code->valid_from)?->format('Y-m-d\TH:i'),
                    'valid_until' => optional($code->valid_until)?->format('Y-m-d\TH:i'),
                    'max_rooms' => $code->max_rooms,
                    'remaining_rooms' => $code->remaining_rooms,
                    'is_active' => $code->is_active,
                    'is_currently_valid' => $code->is_currently_valid,
                    'active_redemptions_count' => $code->active_redemptions_count,
                    'creator' => $code->creator?->name,
                    'created_at' => optional($code->created_at)?->format('d M Y, g:i A'),
                ];
            });

        return Inertia::render('Admin/DiscountCodes/Index', [
            'codes' => $codes,
            'scopeOptions' => collect(DiscountCode::scopeOptions())->map(fn ($label, $value) => [
                'label' => $label,
                'value' => $value,
            ])->values(),
            'discountTypeOptions' => collect(DiscountCode::discountTypeOptions())->map(fn ($label, $value) => [
                'label' => $label,
                'value' => $value,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:discount_codes,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'applies_to' => ['required', 'in:' . implode(',', array_keys(DiscountCode::scopeOptions()))],
            'discount_type' => ['required', 'in:' . implode(',', array_keys(DiscountCode::discountTypeOptions()))],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'max_rooms' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (
            $data['discount_type'] === DiscountCode::TYPE_PERCENTAGE
            && (float) $data['discount_value'] > 100
        ) {
            return back()->withErrors([
                'discount_value' => 'Percentage discounts cannot exceed 100.',
            ])->withInput();
        }

        DiscountCode::create([
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'created_by' => $request->user()?->id,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('admin.discount-codes.index')
            ->with('success', 'Discount code created successfully.');
    }

    public function toggle(DiscountCode $discountCode)
    {
        $discountCode->update([
            'is_active' => ! $discountCode->is_active,
        ]);

        return back()->with('success', $discountCode->is_active
            ? 'Discount code activated.'
            : 'Discount code paused.');
    }

    public function extend(Request $request, DiscountCode $discountCode)
    {
        $data = $request->validate([
            'valid_until' => ['required', 'date', 'after:now'],
        ]);

        $discountCode->update([
            'valid_until' => $data['valid_until'],
        ]);

        return back()->with('success', 'Discount code expiration updated.');
    }

    public function destroy(DiscountCode $discountCode)
    {
        if ($discountCode->redemptions()->exists()) {
            return back()->withErrors([
                'discount_code' => 'This discount code has redemption history and cannot be deleted.',
            ]);
        }

        $discountCode->delete();

        return redirect()->route('admin.discount-codes.index')
            ->with('success', 'Discount code deleted successfully.');
    }
}
