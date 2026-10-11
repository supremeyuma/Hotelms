<?php

namespace App\Console\Commands;

use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild the drink-stock balance from its movement ledger.
 *
 * Spec section 6.2: the movement is the truth and the balance is a rebuildable
 * cache. `full_bottles` is reconstructed as the signed sum of each movement's
 * `quantity_change`, which is exactly what the service applies incrementally.
 *
 * A signed sum is order-independent, so no explicit total order is needed for
 * the balance to be identical on every node (spec section 6, Class 3).
 *
 * `opened_bottles` is deliberately untouched: unlike `full_bottles`, no
 * movement records a change to it, so there is nothing to rebuild it from.
 */
class RebuildDrinkStock extends Command
{
    protected $signature = 'drink-stock:rebuild';

    protected $description = 'Rebuild drink stock balances from movements';

    public function handle(): int
    {
        $this->info('Rebuilding drink stock...');

        DB::transaction(function () {
            $totals = DrinkStockMovement::query()
                ->selectRaw('drink_stock_id, SUM(quantity_change) as total')
                ->groupBy('drink_stock_id')
                ->pluck('total', 'drink_stock_id');

            foreach (DrinkStock::all() as $stock) {
                $stock->full_bottles = max(0, (int) ($totals[$stock->id] ?? 0));
                $stock->save();
            }
        });

        $this->info('Done.');

        return self::SUCCESS;
    }
}
