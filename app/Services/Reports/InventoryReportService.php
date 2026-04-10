<?php
// app/Services/Reports/InventoryReportService.php

namespace App\Services\Reports;

use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryReportService
{
    public function chart(int $days = 30): array
    {
        $from = Carbon::now()->subDays($days);

        $data = InventoryMovement::selectRaw('DATE(created_at) as date, SUM(quantity) as total')
            ->whereDate('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date'),
            'values' => $data->pluck('total'),
        ];
    }

     public function query(array $filters)
    {
        return InventoryMovement::query()
            ->with(['item', 'location', 'staff'])
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['inventory_item_id'] ?? null, fn ($q, $v) => $q->where('inventory_item_id', $v))
            ->when($filters['inventory_location_id'] ?? null, fn ($q, $v) => $q->where('inventory_location_id', $v))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('item', function ($itemQuery) use ($search) {
                    $itemQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            });
    }

    public function summary(): array
    {
        return [
            'usage' => (float) InventoryMovement::query()
                ->whereIn('type', [
                    InventoryMovement::TYPE_OUT,
                    InventoryMovement::TYPE_TRANSFER_OUT,
                    InventoryMovement::TYPE_ADJUSTMENT,
                ])
                ->sum(DB::raw('ABS(quantity)')),
        ];
    }
}
