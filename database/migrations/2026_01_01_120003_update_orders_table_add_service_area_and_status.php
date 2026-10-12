<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $this->changeOrderStatus(['pending', 'preparing', 'ready', 'delivered', 'completed']);

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

        $this->changeOrderStatus(['pending']);
    }

    /**
     * Change the orders.status enum in a driver-aware way.
     *
     * Laravel's Postgres grammar emits an invalid ALTER for enum columns
     * (`... type varchar(255) check (...)`), which aborts the migration
     * transaction. Postgres is therefore handled with explicit constraint
     * management while MySQL keeps the native ENUM definition.
     */
    private function changeOrderStatus(array $allowed): void
    {
        $values = "'".implode("', '", $allowed)."'";
        $default = $allowed[0];
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "orders" DROP CONSTRAINT IF EXISTS "orders_status_check"');
            DB::statement('ALTER TABLE "orders" ALTER COLUMN "status" TYPE varchar(255)');
            DB::statement("ALTER TABLE \"orders\" ALTER COLUMN \"status\" SET DEFAULT '{$default}'");
            DB::statement('ALTER TABLE "orders" ALTER COLUMN "status" SET NOT NULL');
            DB::statement(sprintf(
                'ALTER TABLE "orders" ADD CONSTRAINT "orders_status_check" CHECK ("status" in (%s))',
                $values
            ));

            return;
        }

        if ($driver === 'mysql') {
            DB::statement(sprintf(
                "ALTER TABLE `orders` MODIFY COLUMN `status` ENUM(%s) NOT NULL DEFAULT '%s'",
                $values,
                $default
            ));

            return;
        }

        // sqlite and other drivers: rebuild the column via the schema builder.
        Schema::table('orders', function (Blueprint $table) use ($allowed, $default) {
            $table->enum('status', $allowed)->default($default)->change();
        });
    }
};
