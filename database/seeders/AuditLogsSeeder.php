<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed some audit logs to emulate activity
 */
class AuditLogsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $users = DB::table('users')->pluck('id')->toArray();

        $logs = [];
        $actions = ['booking_created','order_created','image_uploaded','maintenance_ticket_created','inventory_stock_added'];

        foreach (range(1, 25) as $i) {
            $logs[] = [
                'user_id' => $users[$i % count($users)] ?? null,
                'action' => $actions[$i % count($actions)],
                'ip_address' => '127.0.0.1',
                'metadata' => json_encode(['note' => 'Seeded audit log #'.$i]),
                'created_at' => $now->copy()->subHours(rand(1,200)),
                'updated_at' => $now,
            ];
        }

        DB::table('audit_logs')->insert($logs);
    }
}
