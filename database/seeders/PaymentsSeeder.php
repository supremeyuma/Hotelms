<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Create payments for some bookings
 */
class PaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $bookings = DB::table('bookings')->take(8)->get();
        $payments = [];

        foreach ($bookings as $b) {
            $payments[] = [
                'booking_id' => $b->id,
                'amount' => round($b->total_amount * 0.5, 2),
                'method' => 'card',
                'status' => 'paid',
                'transaction_ref' => 'TXN' . strtoupper(substr(md5($b->booking_code), 0, 8)),
                'meta' => json_encode(['gateway' => 'stripe','demo' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('payments')->insert($payments);
    }
}
