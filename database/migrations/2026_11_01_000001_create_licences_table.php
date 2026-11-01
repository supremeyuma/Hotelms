<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licences sold per property.
 *
 * The plaintext key is shown once when it is issued and only its hash is stored,
 * so a leaked database does not hand out working keys. `machine_id` binds an
 * activated licence to one server; terminals and staff clients are covered by
 * the property's server licence and never activate separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 12);
            $table->string('property_name');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('plan')->default('annual');
            $table->unsignedSmallInteger('term_months')->default(12);
            $table->string('status')->default('issued');
            $table->string('machine_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
