<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cursor row says "this node has exchanged up to change N with that peer".
     * Sync needs the same pair in both directions at once: the inbound position
     * (what we have applied from a peer) and the outbound position (how far we
     * have pushed our own log to that peer). A single (node_id, target_node_id)
     * unique index cannot hold both, so the direction joins the key.
     */
    public function up(): void
    {
        Schema::table('sync_cursors', function (Blueprint $table) {
            $table->string('direction')->default('in');
            $table->dropUnique(['node_id', 'target_node_id']);
            $table->unique(['node_id', 'target_node_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_cursors', function (Blueprint $table) {
            $table->dropUnique(['node_id', 'target_node_id', 'direction']);
            $table->unique(['node_id', 'target_node_id']);
            $table->dropColumn('direction');
        });
    }
};
