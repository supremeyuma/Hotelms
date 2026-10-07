<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec 5.4 requires a per-node monotonic sequence, which is what lets a
     * cursor say "everything up to change 412" unambiguously. Spec 5.2 requires
     * the full row snapshot rather than a diff, because a snapshot eliminates
     * an entire class of ordering bug.
     *
     * `old_values` and `new_values` stay in place. They cost nothing and remain
     * useful for audit, but `payload` is the authoritative full snapshot that
     * Part B writes and the applier reads.
     *
     * The unique (node_id, sequence) index is what makes a duplicate sequence
     * visible as an error instead of a silently interleaved log.
     */
    public function up(): void
    {
        Schema::table('sync_changes', function (Blueprint $table) {
            $table->unsignedBigInteger('sequence')->default(0);
            $table->longText('payload')->nullable();
            $table->unique(['node_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_changes', function (Blueprint $table) {
            $table->dropUnique(['node_id', 'sequence']);
            $table->dropColumn(['payload', 'sequence']);
        });
    }
};
