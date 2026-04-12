<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class RepairBookingFinancials extends Command
{
    protected $signature = 'bookings:repair-financials
        {--apply : Persist the repaired values instead of only reporting them}
        {--booking-id= : Repair a single booking id}
        {--days= : Limit the scan to bookings created within the last N days}';

    protected $description = 'Repair stale booking totals and payment statuses using stored discount and override metadata';

    public function handle(BookingService $bookingService): int
    {
        $query = Booking::query()
            ->with(['payments', 'charges:id,booking_id,amount', 'room.roomType', 'roomType', 'rooms.roomType'])
            ->orderBy('id');

        if ($bookingId = $this->option('booking-id')) {
            $query->whereKey($bookingId);
        }

        if ($days = $this->option('days')) {
            $query->where('created_at', '>=', now()->subDays((int) $days));
        }

        $bookings = $query->get();
        $apply = (bool) $this->option('apply');

        if ($bookings->isEmpty()) {
            $this->info('No bookings matched the selected scope.');

            return self::SUCCESS;
        }

        $scanned = 0;
        $changedTotals = 0;
        $changedStatuses = 0;
        $changedMethods = 0;
        $changedBookingStates = 0;

        foreach ($bookings as $booking) {
            $scanned++;

            $targetTotal = $this->repairableEffectiveAmount($booking);
            $originalTotal = $booking->total_amount !== null ? round((float) $booking->total_amount, 2) : null;

            $beforePaymentStatus = (string) $booking->payment_status;
            $beforePaymentMethod = (string) $booking->payment_method;
            $beforeStatus = (string) $booking->status;

            $shouldUpdateTotal = $targetTotal !== null && $originalTotal !== $targetTotal;
            $comparisonTotal = $targetTotal ?? ($originalTotal ?? 0.0);
            $derivedPaymentStatus = $this->resolveBookingPaymentStatus(
                storedStatus: $beforePaymentStatus,
                amountDue: max($comparisonTotal + $this->extraCharges($booking), 0),
                paymentsReceived: $this->paymentsReceived($booking),
            );
            $derivedPaymentMethod = $this->latestSuccessfulPaymentMethod($booking) ?: $beforePaymentMethod;
            $derivedBookingStatus = $beforeStatus === 'pending_payment' && $derivedPaymentStatus === 'paid'
                ? 'confirmed'
                : $beforeStatus;

            if ($shouldUpdateTotal && $apply) {
                $booking->forceFill(['total_amount' => $targetTotal])->save();
                $booking->refresh();
                $booking->load(['payments', 'charges:id,booking_id,amount', 'room.roomType', 'roomType', 'rooms.roomType']);
            }

            if ($apply) {
                $bookingService->syncBookingPaymentState($booking);
                $booking->refresh();
            }

            if ($shouldUpdateTotal) {
                $changedTotals++;
            }

            if ($beforePaymentStatus !== ($apply ? (string) $booking->payment_status : $derivedPaymentStatus)) {
                $changedStatuses++;
            }

            if ($beforePaymentMethod !== ($apply ? (string) $booking->payment_method : $derivedPaymentMethod)) {
                $changedMethods++;
            }

            if ($beforeStatus !== ($apply ? (string) $booking->status : $derivedBookingStatus)) {
                $changedBookingStates++;
            }

            $afterPaymentStatus = $apply ? (string) $booking->payment_status : $derivedPaymentStatus;
            $afterStatus = $apply ? (string) $booking->status : $derivedBookingStatus;

            if ($shouldUpdateTotal || $beforePaymentStatus !== $afterPaymentStatus || $beforeStatus !== $afterStatus) {
                $this->line(sprintf(
                    '%s booking #%d %s total %s -> %s | payment %s -> %s | status %s -> %s',
                    $apply ? 'Updated' : 'Would update',
                    $booking->id,
                    $booking->booking_code ?: '',
                    $originalTotal === null ? 'null' : number_format($originalTotal, 2, '.', ''),
                    $targetTotal === null ? ($originalTotal === null ? 'null' : number_format($originalTotal, 2, '.', '')) : number_format($targetTotal, 2, '.', ''),
                    $beforePaymentStatus !== '' ? $beforePaymentStatus : 'null',
                    $afterPaymentStatus !== '' ? $afterPaymentStatus : 'null',
                    $beforeStatus !== '' ? $beforeStatus : 'null',
                    $afterStatus !== '' ? $afterStatus : 'null',
                ));
            }
        }

        $this->newLine();
        $this->info(($apply ? 'Repair completed.' : 'Dry run completed.') . " Scanned {$scanned} bookings.");
        $this->line("Booking totals " . ($apply ? 'updated' : 'repairable') . ": {$changedTotals}");
        $this->line("Payment statuses changed: {$changedStatuses}");
        $this->line("Payment methods changed: {$changedMethods}");
        $this->line("Booking lifecycle statuses changed: {$changedBookingStates}");

        if (! $apply) {
            $this->comment('Run with --apply to persist the booking total repairs.');
        }

        return self::SUCCESS;
    }

    protected function repairableEffectiveAmount(Booking $booking): ?float
    {
        $details = is_array($booking->details) ? $booking->details : [];
        $override = is_array($details['price_override'] ?? null) ? $details['price_override'] : null;
        $discount = is_array($details['discount'] ?? null) ? $details['discount'] : null;
        $discountPricing = is_array($discount['pricing'] ?? null) ? $discount['pricing'] : null;

        if (isset($override['override_amount'])) {
            return round((float) $override['override_amount'], 2);
        }

        if (isset($discountPricing['total'])) {
            return round((float) $discountPricing['total'], 2);
        }

        return null;
    }

    protected function paymentsReceived(Booking $booking): float
    {
        return round(
            $booking->payments
                ->filter(fn ($payment) => in_array(strtolower((string) $payment->status), ['completed', 'successful', 'paid'], true))
                ->sum(fn ($payment) => (float) ($payment->amount_paid ?? $payment->amount ?? 0)),
            2
        );
    }

    protected function extraCharges(Booking $booking): float
    {
        return round(
            (float) $booking->charges->sum(fn ($charge) => (float) ($charge->amount ?? 0)),
            2
        );
    }

    protected function latestSuccessfulPaymentMethod(Booking $booking): ?string
    {
        $payment = $booking->payments
            ->filter(fn ($item) => in_array(strtolower((string) $item->status), ['completed', 'successful', 'paid'], true))
            ->sortByDesc(fn ($item) => optional($item->paid_at ?? $item->created_at)?->getTimestamp() ?? 0)
            ->first();

        return $payment?->provider ?: $payment?->method;
    }

    protected function resolveBookingPaymentStatus(?string $storedStatus, float $amountDue, float $paymentsReceived): string
    {
        $normalizedStoredStatus = strtolower(trim((string) $storedStatus));

        if ($amountDue <= 0) {
            return $paymentsReceived > 0 ? 'paid' : ($normalizedStoredStatus !== '' ? $normalizedStoredStatus : 'not_required');
        }

        if ($paymentsReceived >= $amountDue) {
            return 'paid';
        }

        if ($paymentsReceived > 0) {
            return 'partial';
        }

        if ($normalizedStoredStatus === 'failed') {
            return 'failed';
        }

        return 'pending';
    }
}
