<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint ) {
            ->string('sync_hlc')->nullable();
            ->string('sync_origin_node_id')->nullable();
            ->softDeletesTz('sync_deleted_at')->nullable();
            ->index(['sync_hlc']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint ) {
            ->dropIndex(['sync_hlc']);
            ->dropColumn(['sync_hlc', 'sync_origin_node_id', 'sync_deleted_at']);
        });
    }
};
