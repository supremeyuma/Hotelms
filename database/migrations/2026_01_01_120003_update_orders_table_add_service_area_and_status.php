<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Try a safe change using the schema builder. This requires doctrine/dbal for change()
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('status', [
                    'pending',
                    'preparing',
                    'ready',
                    'delivered',
                    'completed'
                ])->default('pending')->change();
            });
        } catch (\Throwable $e) {
            // Fallback for MySQL: run raw ALTER TABLE to modify enum
            try {
                $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','preparing','ready','delivered','completed') NOT NULL DEFAULT 'pending'");
                }
                // For other drivers (sqlite/etc) changing enum types may not be supported; user may need to install doctrine/dbal or adjust manually.
            } catch (\Throwable $inner) {
                // swallow - leave migration to have attempted change
            }
        }

        // Add service_area if it doesn't already exist
        if (!Schema::hasColumn('orders', 'service_area')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('service_area', ['kitchen', 'bar'])->nullable();
            });
        }
    }

    public function down(): void
    {
        // Drop service_area if present
        if (Schema::hasColumn('orders', 'service_area')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('service_area');
            });
        }

        // Attempt to shrink status enum back to a single 'pending' option (best-effort)
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('status', ['pending'])->default('pending')->change();
            });
        } catch (\Throwable $e) {
            try {
                $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending') NOT NULL DEFAULT 'pending'");
                }
            } catch (\Throwable $inner) {
                // ignore
            }
        }
    }
};
