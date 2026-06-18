<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_dockets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('device_id')->constrained('pos_devices')->cascadeOnDelete();
            $table->foreignUlid('session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('docket_number');
            $table->string('table_identifier', 50)->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('status', 20)->default('open');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('void_reason')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'docket_number']);
            $table->index('status');
            $table->index('staff_id');
            $table->index('table_identifier');
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_dockets');
    }
};
