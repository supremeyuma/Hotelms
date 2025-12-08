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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            
            $table->decimal('amount', 12, 2)->index()->comment('Amount paid');
            $table->string('method')->comment('Payment method: card, cash, transfer');
            $table->string('status')->default('pending')->index();
            $table->string('transaction_ref')->nullable()->index();
            
            $table->json('meta')->nullable()->comment('Gateway response or logs');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
