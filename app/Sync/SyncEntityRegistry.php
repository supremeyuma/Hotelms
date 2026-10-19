<?php

namespace App\Sync;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\DrinkStockMovement;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosDocketPayment;
use App\Models\PosPrintLog;
use App\Models\PosSession;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StaffProfile;
use App\Sync\Exceptions\UnknownSyncEntityException;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit entity-key to model-class map.
 *
 * A table name arriving over the wire is untrusted input. Resolving it to a
 * class by string manipulation would let a payload name any class in the
 * application.
 *
 * The fifteen entries are exactly the tables in SyncScope that carry sync
 * fields. The four catalogue entries with no model here - inventory_stocks,
 * drink_stock, settings and roles - have no capture path either, so no change
 * for them can be produced today. An unknown key failing loudly is the
 * intended behaviour of a whitelist, not a gap to paper over.
 */
final class SyncEntityRegistry
{
    /** @var array<string, class-string<Model>> */
    private const MAP = [
        'pos_dockets' => PosDocket::class,
        'pos_docket_items' => PosDocketItem::class,
        'pos_docket_payments' => PosDocketPayment::class,
        'pos_print_logs' => PosPrintLog::class,
        'pos_sessions' => PosSession::class,
        'inventory_movements' => InventoryMovement::class,
        'inventory_items' => InventoryItem::class,
        'inventory_locations' => InventoryLocation::class,
        'drink_stock_movements' => DrinkStockMovement::class,
        'payments' => Payment::class,
        'audit_logs' => AuditLog::class,
        'room_types' => RoomType::class,
        'rooms' => Room::class,
        'staff_profiles' => StaffProfile::class,
        'bookings' => Booking::class,
    ];

    /**
     * @return class-string<Model>
     *
     * @throws UnknownSyncEntityException when the key has no registered model
     */
    public static function modelFor(string $entityKey): string
    {
        return self::MAP[$entityKey]
            ?? throw new UnknownSyncEntityException("No model registered for sync entity [{$entityKey}].");
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }
}
