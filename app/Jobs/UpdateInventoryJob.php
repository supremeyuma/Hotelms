<?php

namespace App\Jobs;

use App\Models\InventoryItem;
use App\Models\InventoryLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $itemId;
    public int $quantityUsed;
    public int|null $staffId;

    public function __construct(int $itemId, int $quantityUsed, ?int $staffId)
    {
        $this->itemId = $itemId;
        $this->quantityUsed = $quantityUsed;
        $this->staffId = $staffId;
    }

    public function handle(): void
    {
        $item = InventoryItem::find($this->itemId);

        if (!$item) return;

        // Reduce stock
        $item->decrement('quantity', $this->quantityUsed);

        // Log
        InventoryLog::create([
            'inventory_item_id' => $item->id,
            'change' => -$this->quantityUsed,
            'staff_id' => $this->staffId
        ]);
    }
}
