<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('event_tickets', 'amount_paid')) {
                $table->renameColumn('amount_paid', 'amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('event_tickets', 'amount')) {
                $table->renameColumn('amount', 'amount_paid');
            }
        });
    }
};
