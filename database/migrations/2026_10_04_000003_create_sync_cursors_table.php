<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("sync_cursors", function (Blueprint $table) {
            $table->id();
            $table->string("node_id");
            $table->string("target_node_id");
            $table->string("cursor_hlc")->nullable();
            $table->timestamp("last_synced_at")->nullable();
            $table->timestamps();
            $table->unique(["node_id", "target_node_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sync_cursors");
    }
};
