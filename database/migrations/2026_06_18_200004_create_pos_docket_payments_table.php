<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_docket_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('docket_id')->constrained('pos_dockets')->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->decimal('change_given', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('docket_id');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_docket_payments');
    }
};
