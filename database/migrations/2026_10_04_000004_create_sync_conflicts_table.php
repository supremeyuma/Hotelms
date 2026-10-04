<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("sync_conflicts", function (Blueprint $table) {
            $table->id();
            $table->string("table_name");
            $table->unsignedBigInteger("row_id");
            $table->longText("local_change")->nullable();
            $table->longText("remote_change")->nullable();
            $table->string("status")->default("open");
            $table->longText("resolution")->nullable();
            $table->string("resolved_by")->nullable();
            $table->timestamp("resolved_at")->nullable();
            $table->timestamps();
            $table->index(["table_name", "row_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sync_conflicts");
    }
};
