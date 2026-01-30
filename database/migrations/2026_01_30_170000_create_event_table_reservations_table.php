<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_table_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone')->nullable();
            $table->string('table_number')->default('TBD');
            $table->integer('number_of_guests');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('payment_method', 50)->default('online');
            $table->string('payment_reference')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'refunded'])->default('pending');
            $table->string('qr_code')->unique();
            $table->text('special_requests')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            
            $table->index(['event_id', 'status']);
            $table->index(['payment_status']);
            $table->index(['qr_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_table_reservations');
    }
};