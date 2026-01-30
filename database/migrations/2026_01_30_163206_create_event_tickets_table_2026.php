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
        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_type_id')->nullable()->constrained('event_ticket_types')->onDelete('set null');
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('amount_paid', 10, 2);
            $table->string('payment_method')->default('online');
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, refunded
            $table->string('qr_code')->unique()->nullable();
            $table->datetime('checked_in_at')->nullable();
            $table->datetime('refund_requested_at')->nullable();
            $table->datetime('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['event_id', 'status']);
            $table->index(['payment_status']);
            $table->unique(['qr_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_tickets');
    }
};
