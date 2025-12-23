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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable(); // soft reservation expiry
            $table->integer('adults')->nullable();
            $table->integer('children')->nullable();
            $table->text('special_requests')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            //
        });
    }
};
