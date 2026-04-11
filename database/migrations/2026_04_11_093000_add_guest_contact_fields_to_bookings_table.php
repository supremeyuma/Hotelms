<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('guest_phone');
            }

            if (! Schema::hasColumn('bookings', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            }

            if (! Schema::hasColumn('bookings', 'purpose_of_stay')) {
                $table->string('purpose_of_stay')->nullable()->after('emergency_contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('bookings', 'emergency_contact_name') ? 'emergency_contact_name' : null,
                Schema::hasColumn('bookings', 'emergency_contact_phone') ? 'emergency_contact_phone' : null,
                Schema::hasColumn('bookings', 'purpose_of_stay') ? 'purpose_of_stay' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
