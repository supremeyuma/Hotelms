<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->string('sync_hlc')->nullable();
            $table->string('sync_origin_node_id')->nullable();
            $table->softDeletesTz('sync_deleted_at')->nullable();
            $table->index(['sync_hlc']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropIndex(['sync_hlc']);
            $table->dropColumn(['sync_hlc', 'sync_origin_node_id', 'sync_deleted_at']);
        });
    }
};
