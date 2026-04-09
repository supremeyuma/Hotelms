<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'room_type_id')) {
                $table->foreignId('room_type_id')->nullable()->after('room_id')->constrained('room_types')->nullOnDelete();
            }

            if (! Schema::hasColumn('bookings', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('bookings', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('children');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'room_type_id')) {
                $table->dropConstrainedForeignId('room_type_id');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('bookings', 'guest_name') ? 'guest_name' : null,
                Schema::hasColumn('bookings', 'quantity') ? 'quantity' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
