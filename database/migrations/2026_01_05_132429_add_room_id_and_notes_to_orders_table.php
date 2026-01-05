<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // Room reference (nullable for legacy orders)
            $table->foreignId('room_id')
                ->nullable()
                ->after('booking_id')
                ->constrained()
                ->nullOnDelete();

            // Optional guest / staff notes
            $table->text('notes')
                ->nullable()
                ->after('service_area');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropForeign(['room_id']);
            $table->dropColumn(['room_id', 'notes']);
        });
    }
};
