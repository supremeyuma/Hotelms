<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_points', function (Blueprint $table) {
            $table->id();
            $table->string('customer_email')->index();
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('points', 10, 2)->default(0);
            $table->decimal('amount_spent', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->enum('type', ['earned', 'redeemed'])->default('earned');
            $table->timestamps();
            
            $table->index(['customer_email', 'type']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_points');
    }
};