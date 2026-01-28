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
        $location = InventoryLocation::where('type', 'main_store')->firstOrFail();

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
                    reason: "Room cleaning (Room {$room->number})"
                );
            }
        });
    }
}
