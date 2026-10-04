<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("sync_nodes", function (Blueprint $table) {
            $table->id();
            $table->string("node_id")->unique();
            $table->string("name")->nullable();
            $table->string("type")->nullable();
            $table->string("status")->default("active");
            $table->json("meta")->nullable();
            $table->timestamp("last_seen_at")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sync_nodes");
    }
};
