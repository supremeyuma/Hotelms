<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 5.4: a `sync_conflicts` row holds "local payload, remote payload,
 * policy and resolution", so a human can see why a merge stopped without
 * diffing two blobs.
 *
 * Policy is derivable from `table_name` through SyncScope, but the rule that
 * fired - `overlapping_reservation`, `device_already_in_use`,
 * `captured_then_refunded` - is not. The catalogue returns it for exactly
 * this purpose. The original table had nowhere to put it, and the applier
 * gained the ability to record conflicts in Phase 2R Part B Task 4, so the
 * column lands here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_conflicts', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('remote_change');
        });
    }

    public function down(): void
    {
        Schema::table('sync_conflicts', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};