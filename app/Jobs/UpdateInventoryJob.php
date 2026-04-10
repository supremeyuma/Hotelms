<?php

namespace App\Jobs;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $itemId;
    public float $quantityUsed;
    public int|null $staffId;

    public function __construct(int $itemId, float $quantityUsed, ?int $staffId)
    {
        $this->itemId = $itemId;
        $this->quantityUsed = $quantityUsed;
        $this->staffId = $staffId;
    }

    public function handle(InventoryService $inventory): void
    {
        $item = InventoryItem::find($this->itemId);

        if (! $item) {
            return;
        }

        $location = InventoryLocation::where('type', InventoryLocation::TYPE_MAIN_STORE)->first();

        if (! $location) {
            return;
        }

        $inventory->consumeStock(
            item: $item,
            location: $location,
            quantity: $this->quantityUsed,
            staffId: $this->staffId,
            reason: 'Queued inventory update',
            meta: ['source' => self::class]
        );
    }
}
