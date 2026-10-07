<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec 4.4 keys the node registry by (cloud, property) and spec 5.4 lists a
     * credential column. Neither exists on sync_nodes.
     *
     * `credential` holds a HASH, never a plaintext shared secret. It is the LAN
     * and cloud authentication surface and Phase 4R authenticates against it.
     */
    public function up(): void
    {
        Schema::table('sync_nodes', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('credential')->nullable();
            $table->index(['property_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_nodes', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'type']);
            $table->dropColumn(['credential', 'property_id']);
        });
    }
};
