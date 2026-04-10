<?php

namespace App\Services;

use App\Models\Room;
use App\Models\InventoryLocation;
use App\Models\CleaningInventoryTemplate;
use Illuminate\Support\Facades\DB;

class CleaningInventoryService
{
    protected InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    public function consumeForRoom(Room $room, int $staffId): void
    {
        $location = InventoryLocation::where('type', InventoryLocation::TYPE_MAIN_STORE)->firstOrFail();

        $templates = CleaningInventoryTemplate::where(
            'room_type_id',
            $room->room_type_id
        )->get();

        DB::transaction(function () use ($templates, $room, $location, $staffId) {
            foreach ($templates as $template) {
                $this->inventory->consumeStock(
                    item: $template->inventoryItem,
                    location: $location,
                    quantity: $template->quantity,
                    staffId: $staffId,
                    reason: "Room cleaning (Room {$room->number})",
                    referenceType: Room::class,
                    referenceId: $room->id,
                    meta: [
                        'room_type_id' => $room->room_type_id,
                        'cleaning_template_id' => $template->id,
                    ]
                );
            }
        });
    }
}
