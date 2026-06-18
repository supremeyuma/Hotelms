<?php

namespace App\Services;

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosDocketPayment;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\Booking;
use App\Services\AuditLoggerService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubPosService
{
    public function __construct(
        private PricingService $pricing,
        private AuditLoggerService $audit,
        private DrinkStockService $stock,
    ) {}

    public function getOrCreateSession(PosDevice $device, User $user): PosSession
    {
        $session = PosSession::where('device_id', $device->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if ($session) {
            return $session;
        }

        return DB::transaction(function () use ($device, $user) {
            $session = PosSession::create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'cash_start' => 0,
                'status' => 'open',
            ]);

            $this->audit->log('pos_session_opened', $session, $session->id, [
                'device_id' => $device->id,
                'user_id' => $user->id,
            ]);

            return $session;
        });
    }

    public function openDocket(PosSession $session, User $staff, ?string $tableIdentifier = null, ?string $customerName = null, ?int $bookingId = null, ?int $roomId = null): PosDocket
    {
        return DB::transaction(function () use ($session, $staff, $tableIdentifier, $customerName, $bookingId, $roomId) {
            $maxNumber = PosDocket::where('session_id', $session->id)->max('docket_number') ?? 0;

            $docket = PosDocket::create([
                'device_id' => $session->device_id,
                'session_id' => $session->id,
                'staff_id' => $staff->id,
                'docket_number' => $maxNumber + 1,
                'table_identifier' => $tableIdentifier,
                'customer_name' => $customerName,
                'booking_id' => $bookingId,
                'room_id' => $roomId,
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $this->audit->log('pos_docket_opened', $docket, $docket->id, [
                'staff_id' => $staff->id,
                'session_id' => $session->id,
                'table' => $tableIdentifier,
            ]);

            return $docket;
        });
    }

    public function addItem(PosDocket $docket, MenuItem $menuItem, int $quantity, User $staff): PosDocket
    {
        if ($docket->status !== 'open') {
            throw new \RuntimeException('Cannot add items to a closed docket.');
        }

        return DB::transaction(function () use ($docket, $menuItem, $quantity, $staff) {
            $subtotal = $menuItem->price * $quantity;

            PosDocketItem::create([
                'docket_id' => $docket->id,
                'menu_item_id' => $menuItem->id,
                'item_name' => $menuItem->name,
                'quantity' => $quantity,
                'unit_price' => $menuItem->price,
                'subtotal' => $subtotal,
            ]);

            $docket->subtotal += $subtotal;
            $pricing = $this->pricing->calculatePricing($docket->subtotal);
            $docket->vat = $pricing['vat'];
            $docket->service_charge = $pricing['service_charge'];
            $docket->total = $pricing['total'];
            $docket->save();

            $this->audit->log('pos_docket_item_added', $docket, $docket->id, [
                'menu_item' => $menuItem->name,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            return $docket->fresh();
        });
    }

    public function closeDocket(PosDocket $docket, array $payments, ?User $closedBy = null): PosDocket
    {
        if ($docket->status !== 'open') {
            throw new \RuntimeException('Docket is already closed.');
        }

        return DB::transaction(function () use ($docket, $payments, $closedBy) {
            $totalPaid = 0;

            foreach ($payments as $payment) {
                PosDocketPayment::create([
                    'docket_id' => $docket->id,
                    'payment_method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'change_given' => $payment['change_given'] ?? 0,
                    'meta' => $payment['meta'] ?? null,
                ]);

                $totalPaid += $payment['amount'];

                if ($payment['method'] === 'room_charge' && $docket->booking_id) {
                    $booking = Booking::find($docket->booking_id);
                    if ($booking) {
                        $roomId = $docket->room_id ?? $booking->rooms->first()?->id;
                        app(BillingService::class)->addCharge(
                            $booking,
                            $roomId,
                            "Club Lounge — Docket #{$docket->docket_number}",
                            $payment['amount'],
                            'pos_sale'
                        );
                    }
                }
            }

            $docket->status = 'closed';
            $docket->closed_by = $closedBy?->id ?? $docket->staff_id;
            $docket->closed_at = now();
            $docket->save();

            $session = $docket->session;
            $session->total_sales += $docket->total;
            $session->docket_count += 1;
            $session->save();

            foreach ($payments as $payment) {
                if ($payment['method'] === 'stock_consumption' || $payment['method'] === 'cash') {
                    foreach ($docket->items as $item) {
                        try {
                            $this->stock->consumeForSale($item->menuItem, $item->quantity, $docket->staff_id, $docket);
                        } catch (\Exception $e) {
                            report($e);
                        }
                    }
                }
            }

            $this->audit->log('pos_docket_closed', $docket, $docket->id, [
                'total' => $docket->total,
                'payments' => $payments,
                'item_count' => $docket->items()->count(),
            ]);

            return $docket->fresh(['items', 'payments']);
        });
    }

    public function voidDocket(PosDocket $docket, string $reason, User $voidedBy): PosDocket
    {
        if ($docket->status !== 'open') {
            throw new \RuntimeException('Cannot void a closed docket.');
        }

        return DB::transaction(function () use ($docket, $reason, $voidedBy) {
            $docket->status = 'voided';
            $docket->void_reason = $reason;
            $docket->closed_by = $voidedBy->id;
            $docket->closed_at = now();
            $docket->save();

            $this->audit->log('pos_docket_voided', $docket, $docket->id, [
                'reason' => $reason,
                'voided_by' => $voidedBy->id,
            ]);

            return $docket;
        });
    }
}
