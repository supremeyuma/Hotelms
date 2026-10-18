<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Free orders.status on PostgreSQL to match MySQL.
     *
     * 2026_01_06_184410_change_orders_status_to_string.php un-enum'd
     * orders.status but only ran its body for the mysql driver, leaving
     * PostgreSQL with the stale orders_status_check created by
     * 2026_01_01_120003 (pending | preparing | ready | delivered |
     * completed). The app writes processing, confirmed and cancelled via
     * App\Enums\OrderStatus, so on PostgreSQL order creation, the staff
     * state machine, and guest cancellation all failed with SQLSTATE 23514
     * while MySQL (the production engine) accepted them on a free-form
     * VARCHAR(30) with default 'pending'.
     *
     * Dropping the contradicted check makes PostgreSQL no stricter than the
     * de-facto production semantics; the default 'pending' is unchanged.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE "orders" DROP CONSTRAINT IF EXISTS "orders_status_check"');
    }

    public function down(): void
    {
        // Intentionally a no-op on every driver. Re-adding orders_status_check
        // would resurrect the PostgreSQL-only restriction that broke three
        // production flows on a schema MySQL accepts; orders.status is
        // contractually a free-form string defaulting to 'pending'. Rolling
        // this back is only ever correct together with an app-level enum
        // realignment, which is a separate, deliberate change.
    }
};
