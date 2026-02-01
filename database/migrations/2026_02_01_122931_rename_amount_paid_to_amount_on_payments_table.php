<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_table_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('event_table_reservations', 'amount_paid')) {
                $table->renameColumn('amount_paid', 'amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_table_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('event_table_reservations', 'amount')) {
                $table->renameColumn('amount', 'amount_paid');
            }
        });
    }
};
