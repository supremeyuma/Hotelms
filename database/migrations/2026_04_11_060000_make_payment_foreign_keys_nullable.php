<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'booking_id')) {
                $table->foreignId('booking_id')->nullable()->change();
            }

            if (Schema::hasColumn('payments', 'room_id')) {
                $table->foreignId('room_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'booking_id')) {
                $table->foreignId('booking_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('payments', 'room_id')) {
                $table->foreignId('room_id')->nullable(false)->change();
            }
        });
    }
};
