<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("sync_changes", function (Blueprint $table) {
            $table->id();
            $table->string("table_name");
            $table->unsignedBigInteger("row_id");
            $table->string("operation");
            $table->longText("old_values")->nullable();
            $table->longText("new_values")->nullable();
            $table->string("node_id");
            $table->string("hlc");
            $table->string("parent_hlc")->nullable();
            $table->timestamp("applied_at")->nullable();
            $table->timestamps();
            $table->index(["table_name", "row_id"]);
            $table->index(["hlc"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sync_changes");
    }
};
