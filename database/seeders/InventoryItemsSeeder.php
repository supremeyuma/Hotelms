<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed inventory items and a few initial quantities
 */
class InventoryItemsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $items = [
            ['name'=>'Bath Towel','sku'=>'TOWEL-001','quantity'=>200,'unit'=>'pcs','meta'=>json_encode(['reorder'=>50]),'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Shampoo Bottle','sku'=>'SHAM-001','quantity'=>500,'unit'=>'pcs','meta'=>json_encode(['reorder'=>100]),'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Soap Bar','sku'=>'SOAP-001','quantity'=>600,'unit'=>'pcs','meta'=>json_encode(['reorder'=>150]),'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Laundry Detergent','sku'=>'LD-001','quantity'=>100,'unit'=>'kg','meta'=>json_encode([]),'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'Coffee Beans','sku'=>'COF-001','quantity'=>80,'unit'=>'kg','meta'=>json_encode([]),'created_at'=>$now,'updated_at'=>$now],
        ];

        DB::table('inventory_items')->insert($items);
    }
}
