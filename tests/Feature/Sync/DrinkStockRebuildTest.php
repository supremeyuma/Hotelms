<?php

namespace Tests\Feature\Sync;

use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use App\Models\MenuItem;
use App\Services\DrinkStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DrinkStockRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_matches_the_incremental_balance(): void
    {
        $item = MenuItem::factory()->create();
        $service = app(DrinkStockService::class);

        $service->addStock($item, 24);
        $service->consumeForSale($item, 6);
        $service->consumeForSale($item, 2);

        $before = (int) DrinkStock::where('menu_item_id', $item->id)->value('full_bottles');
        $this->assertSame(16, $before);

        Artisan::call('drink-stock:rebuild');

        $after = (int) DrinkStock::where('menu_item_id', $item->id)->value('full_bottles');

        $this->assertSame($before, $after);
        $this->assertSame(16, $after);
    }

    public function test_rebuild_is_order_independent(): void
    {
        $first = $this->stockWithOrder([10, -3, 5]);
        $second = $this->stockWithOrder([5, 10, -3]);

        Artisan::call('drink-stock:rebuild');

        $firstTotal = (int) DrinkStock::where('menu_item_id', $first->id)->value('full_bottles');
        $secondTotal = (int) DrinkStock::where('menu_item_id', $second->id)->value('full_bottles');

        $this->assertSame(12, $firstTotal);
        $this->assertSame($firstTotal, $secondTotal);
    }

    /**
     * A drink stock with the given quantity changes recorded in order.
     *
     * @param  list<int>  $changes
     */
    private function stockWithOrder(array $changes): MenuItem
    {
        $item = MenuItem::factory()->create();

        $stock = DrinkStock::create([
            'menu_item_id'       => $item->id,
            'full_bottles'       => 0,
            'opened_bottles'     => 0,
            'pours_per_bottle'   => 25,
            'low_stock_threshold' => 3,
            'is_active'          => true,
        ]);

        $running = 0;

        foreach ($changes as $change) {
            $before = $running;
            $running += $change;

            DrinkStockMovement::create([
                'drink_stock_id'      => $stock->id,
                'type'                => $change >= 0 ? 'delivery' : 'sale',
                'quantity_change'     => $change,
                'full_bottles_before' => $before,
                'full_bottles_after'  => $running,
            ]);
        }

        return $item;
    }
}
