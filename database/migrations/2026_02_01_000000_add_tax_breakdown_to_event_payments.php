<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('event_tickets') && !Schema::hasColumn('event_tickets', 'base_amount')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->decimal('base_amount', 10, 2)->nullable();
                $table->decimal('vat_amount', 10, 2)->nullable();
                $table->decimal('service_charge_amount', 10, 2)->nullable();
            });
        }

        if (Schema::hasTable('event_table_reservations') && !Schema::hasColumn('event_table_reservations', 'base_amount')) {
            Schema::table('event_table_reservations', function (Blueprint $table) {
                $table->decimal('base_amount', 10, 2)->nullable();
                $table->decimal('vat_amount', 10, 2)->nullable();
                $table->decimal('service_charge_amount', 10, 2)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('event_tickets', 'base_amount')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->dropColumn(['base_amount', 'vat_amount', 'service_charge_amount']);
            });
        }

        if (Schema::hasColumn('event_table_reservations', 'base_amount')) {
            Schema::table('event_table_reservations', function (Blueprint $table) {
                $table->dropColumn(['base_amount', 'vat_amount', 'service_charge_amount']);
            });
        }
    }
};
