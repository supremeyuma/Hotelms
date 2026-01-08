<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('code')->nullable()->after('name'); // optional short code
            $table->string('display_name')->nullable(); // marketing name
            $table->string('slug')->nullable(); // emerald-suite
            $table->integer('floor')->nullable();

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            //
        });
    }
};
