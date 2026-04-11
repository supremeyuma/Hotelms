<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\DiscountCode;
use App\Models\DiscountCodeRedemption;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountCodeService
{
    public function __construct(
        protected PricingService $pricingService
    ) {
    }

    public function previewForBookingSelection(array $bookingData, string $rawCode): array
    {
        $roomType = RoomType::findOrFail($bookingData['room_type_id']);
        $roomCount = max((int) ($bookingData['quantity'] ?? count($bookingData['selected_room_ids'] ?? [])), 1);
        $nights = $this->resolveNights($bookingData['check_in'], $bookingData['check_out']);
        $baseAmount = round((float) $roomType->base_price * $roomCount * $nights, 2);
        $discountCode = $this->resolveCode($rawCode);

        $this->assertBookableDiscount($discountCode, $roomCount);

        return $this->buildPreview($discountCode, $baseAmount, $roomCount, $nights);
    }

    public function previewForBooking(Booking $booking, string $rawCode): array
    {
        $booking->loadMissing('roomType');

        $discountCode = $this->resolveCode($rawCode);
        $roomCount = max((int) ($booking->quantity ?: 1), 1);
        $baseAmount = $this->baseRoomAmountForBooking($booking);
        $existingRedemption = $booking->discountRedemption;

        $this->assertBookableDiscount(
            $discountCode,
            $roomCount,
            $existingRedemption?->discount_code_id === $discountCode->id ? $booking : null
        );

        return $this->buildPreview(
            $discountCode,
            $baseAmount,
            $roomCount,
            $this->resolveNights($booking->check_in, $booking->check_out)
        );
    }

    public function previewForCharge(float $amount, string $rawCode, string $scope, int $roomCount = 1): array
    {
        $discountCode = $this->resolveCode($rawCode);

        $this->assertDiscountForScope($discountCode, $scope, $roomCount);

        $discountAmount = $this->calculateDiscountAmount($discountCode, $amount);

        return [
            'discount_code_id' => $discountCode->id,
            'name' => $discountCode->name,
            'code' => $discountCode->code,
            'scope' => $discountCode->applies_to,
            'scope_label' => DiscountCode::scopeOptions()[$discountCode->applies_to] ?? $discountCode->applies_to,
            'discount_type' => $discountCode->discount_type,
            'discount_value' => (float) $discountCode->discount_value,
            'discount_amount' => $discountAmount,
            'base_amount' => round($amount, 2),
            'net_amount' => round(max($amount - $discountAmount, 0), 2),
            'room_count' => $roomCount,
        ];
    }

    public function reserveForBooking(Booking $booking, array $preview): void
    {
        DB::transaction(function () use ($booking, $preview) {
            $booking->loadMissing('roomType', 'discountRedemption.discountCode');

            $discountCode = DiscountCode::query()->lockForUpdate()->findOrFail($preview['discount_code_id']);
            $roomCount = max((int) ($booking->quantity ?: 1), 1);

            $this->assertBookableDiscount($discountCode, $roomCount, $booking);

            $redemption = $booking->discountRedemption;

            if ($redemption && $redemption->discount_code_id !== $discountCode->id) {
                $this->releaseRedemption($redemption);
                $redemption = null;
            }

            $payload = [
                'scope' => $discountCode->applies_to,
                'status' => 'reserved',
                'rooms_used' => $roomCount,
                'base_amount' => $preview['pricing']['base_amount'],
                'discount_amount' => $preview['discount_amount'],
                'meta' => [
                    'code' => $discountCode->code,
                    'discount_type' => $discountCode->discount_type,
                    'discount_value' => $discountCode->discount_value,
                    'pricing' => $preview['pricing'],
                ],
                'released_at' => null,
            ];

            if ($redemption) {
                $redemption->update($payload);
            } else {
                $booking->discountRedemption()->create([
                    'discount_code_id' => $discountCode->id,
                    ...$payload,
                ]);
            }

            $this->updateBookingTotals($booking, $preview);
        });
    }

    public function removeFromBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->loadMissing('roomType', 'discountRedemption');

            if ($booking->discountRedemption) {
                $this->releaseRedemption($booking->discountRedemption);
            }

            $this->updateBookingTotals($booking, null);
        });
    }

    public function reserveForCharge(Charge $charge, array $preview): void
    {
        DB::transaction(function () use ($charge, $preview) {
            $discountCode = DiscountCode::query()->lockForUpdate()->findOrFail($preview['discount_code_id']);

            $this->assertDiscountForScope(
                $discountCode,
                $preview['scope'],
                max((int) ($preview['room_count'] ?? 1), 1)
            );

            $charge->discountRedemption()->updateOrCreate(
                [
                    'discount_code_id' => $discountCode->id,
                ],
                [
                    'scope' => $discountCode->applies_to,
                    'status' => 'applied',
                    'rooms_used' => max((int) ($preview['room_count'] ?? 1), 1),
                    'base_amount' => $preview['base_amount'],
                    'discount_amount' => $preview['discount_amount'],
                    'meta' => [
                        'code' => $discountCode->code,
                        'discount_type' => $discountCode->discount_type,
                        'discount_value' => $discountCode->discount_value,
                        'net_amount' => $preview['net_amount'],
                    ],
                ]
            );
        });
    }

    public function markAppliedForBooking(Booking $booking): void
    {
        $booking->load('discountRedemption');

        if (! $booking->discountRedemption || $booking->discountRedemption->released_at) {
            return;
        }

        $booking->discountRedemption->update([
            'status' => 'applied',
        ]);
    }

    public function releaseForBooking(Booking $booking): void
    {
        $booking->load('discountRedemption');

        if ($booking->discountRedemption && ! $booking->discountRedemption->released_at) {
            $this->releaseRedemption($booking->discountRedemption);
        }
    }

    public function bookingDiscountSummary(?Booking $booking): ?array
    {
        if (! $booking) {
            return null;
        }

        $booking->loadMissing('discountRedemption.discountCode');
        $redemption = $booking->discountRedemption;

        if (! $redemption || $redemption->released_at) {
            return null;
        }

        $code = $redemption->discountCode;
        $pricing = $redemption->meta['pricing'] ?? [];

        return [
            'name' => $code?->name,
            'code' => $code?->code ?? $redemption->meta['code'] ?? null,
            'scope' => $redemption->scope,
            'scope_label' => DiscountCode::scopeOptions()[$redemption->scope] ?? ucfirst(str_replace('_', ' ', $redemption->scope)),
            'discount_type' => $code?->discount_type ?? $redemption->meta['discount_type'] ?? null,
            'discount_value' => (float) ($code?->discount_value ?? $redemption->meta['discount_value'] ?? 0),
            'discount_amount' => (float) $redemption->discount_amount,
            'pricing' => [
                'base_amount' => (float) ($pricing['base_amount'] ?? $redemption->base_amount),
                'vat' => (float) ($pricing['vat'] ?? 0),
                'service_charge' => (float) ($pricing['service_charge'] ?? 0),
                'total' => (float) ($pricing['total'] ?? $booking->total_amount),
            ],
        ];
    }

    public function buildPricing(float $baseAmount): array
    {
        return $this->pricingService->calculatePricing($baseAmount);
    }

    protected function updateBookingTotals(Booking $booking, ?array $preview): void
    {
        $baseAmount = $preview['pricing']['base_amount'] ?? $this->baseRoomAmountForBooking($booking);
        $pricing = $preview['pricing'] ?? $this->buildPricing($baseAmount);
        $details = $booking->details ?? [];

        if ($preview) {
            $details['discount'] = [
                'discount_code_id' => $preview['discount_code_id'],
                'code' => $preview['code'],
                'name' => $preview['name'],
                'scope' => $preview['scope'],
                'discount_type' => $preview['discount_type'],
                'discount_value' => $preview['discount_value'],
                'discount_amount' => $preview['discount_amount'],
                'pricing' => $pricing,
            ];
        } else {
            unset($details['discount']);
        }

        $booking->update([
            'total_amount' => $pricing['total'],
            'details' => $details,
        ]);
    }

    protected function assertBookableDiscount(DiscountCode $discountCode, int $roomCount, ?Booking $ignoreBooking = null): void
    {
        if (! $discountCode->is_currently_valid) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code is not active for use right now.',
            ]);
        }

        if ($discountCode->applies_to !== DiscountCode::APPLIES_TO_ROOM_RATE) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code cannot be used for room booking payment.',
            ]);
        }

        $this->assertRoomCap($discountCode, $roomCount, $ignoreBooking);
    }

    protected function assertDiscountForScope(DiscountCode $discountCode, string $scope, int $roomCount, mixed $ignoreRedeemable = null): void
    {
        if (! $discountCode->is_currently_valid) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code is not active for use right now.',
            ]);
        }

        if ($discountCode->applies_to !== $scope) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code does not match the charge category you selected.',
            ]);
        }

        $this->assertRoomCap($discountCode, $roomCount, $ignoreRedeemable);
    }

    protected function assertRoomCap(DiscountCode $discountCode, int $roomCount, mixed $ignoreRedeemable = null): void
    {
        if ($discountCode->max_rooms === null) {
            return;
        }

        $usedRooms = (int) $discountCode->redemptions()
            ->whereIn('status', ['reserved', 'applied'])
            ->whereNull('released_at')
            ->when($ignoreRedeemable, function ($query) use ($ignoreRedeemable) {
                $query->where(function ($nested) use ($ignoreRedeemable) {
                    $nested
                        ->where('redeemable_type', '!=', $ignoreRedeemable->getMorphClass())
                        ->orWhere('redeemable_id', '!=', $ignoreRedeemable->getKey());
                });
            })
            ->sum('rooms_used');

        if (($usedRooms + $roomCount) > $discountCode->max_rooms) {
            throw ValidationException::withMessages([
                'discount_code' => 'This discount code has reached its room usage limit.',
            ]);
        }
    }

    protected function buildPreview(DiscountCode $discountCode, float $baseAmount, int $roomCount, int $nights): array
    {
        $discountAmount = $this->calculateDiscountAmount($discountCode, $baseAmount);
        $discountedBase = max($baseAmount - $discountAmount, 0);
        $pricing = $this->buildPricing($discountedBase);

        return [
            'discount_code_id' => $discountCode->id,
            'name' => $discountCode->name,
            'code' => $discountCode->code,
            'scope' => $discountCode->applies_to,
            'scope_label' => DiscountCode::scopeOptions()[$discountCode->applies_to] ?? $discountCode->applies_to,
            'discount_type' => $discountCode->discount_type,
            'discount_value' => (float) $discountCode->discount_value,
            'discount_amount' => $discountAmount,
            'room_count' => $roomCount,
            'nights' => $nights,
            'pricing' => $pricing,
        ];
    }

    protected function calculateDiscountAmount(DiscountCode $discountCode, float $baseAmount): float
    {
        if ($discountCode->discount_type === DiscountCode::TYPE_FIXED) {
            return round(min($baseAmount, (float) $discountCode->discount_value), 2);
        }

        return round(min($baseAmount, $baseAmount * ((float) $discountCode->discount_value / 100)), 2);
    }

    protected function resolveCode(string $rawCode): DiscountCode
    {
        $code = strtoupper(trim($rawCode));

        $discountCode = DiscountCode::query()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->first();

        if (! $discountCode) {
            throw ValidationException::withMessages([
                'discount_code' => 'That discount code could not be found.',
            ]);
        }

        return $discountCode;
    }

    protected function resolveNights(mixed $checkIn, mixed $checkOut): int
    {
        $checkInDate = \Carbon\Carbon::parse($checkIn)->startOfDay();
        $checkOutDate = \Carbon\Carbon::parse($checkOut)->startOfDay();

        return max($checkInDate->diffInDays($checkOutDate), 1);
    }

    protected function baseRoomAmountForBooking(Booking $booking): float
    {
        $nightlyRate = (float) ($booking->nightly_rate ?: $booking->roomType?->base_price ?: 0);
        $roomCount = max((int) ($booking->quantity ?: 1), 1);
        $nights = $this->resolveNights($booking->check_in, $booking->check_out);

        return round($nightlyRate * $roomCount * $nights, 2);
    }

    protected function releaseRedemption(DiscountCodeRedemption $redemption): void
    {
        $redemption->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }
}
