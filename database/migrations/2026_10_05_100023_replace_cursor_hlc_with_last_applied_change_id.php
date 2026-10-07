<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec 5.4 defines a cursor as (peer_node_id, last_applied_change_id). An
     * HLC string is not that, and cannot express "everything up to change 412"
     * precisely -- an HLC orders writes, it does not count them.
     *
     * The existing unique (node_id, target_node_id) already matches the
     * (peer, cursor) pairing, so it stays untouched.
     */
    public function up(): void
    {
        Schema::table('sync_cursors', function (Blueprint $table) {
            $table->unsignedBigInteger('last_applied_change_id')->nullable();
            $table->dropColumn('cursor_hlc');
        });
    }

    public function down(): void
    {
        Schema::table('sync_cursors', function (Blueprint $table) {
            $table->string('cursor_hlc')->nullable();
            $table->dropColumn('last_applied_change_id');
        });
    }
};
