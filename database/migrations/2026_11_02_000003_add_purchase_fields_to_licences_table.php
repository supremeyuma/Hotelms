<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a licence was sold as.
 *
 * A licence already records the property and term; these columns record the tier
 * it was priced at, the room count the customer declared, the currency and the
 * amount paid. They make a licence traceable to a sale without joining the order,
 * and support can tell an annual $600 licence from a ₦180,000 one at a glance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licences', function (Blueprint $table) {
            $table->foreignId('licence_plan_id')->nullable()->after('id')
                ->constrained('licence_plans')->nullOnDelete();
            $table->unsignedSmallInteger('room_count')->nullable()->after('property_name');
            $table->char('currency', 3)->nullable()->after('term_months');
            $table->decimal('monthly_amount', 12, 2)->nullable()->after('currency');
            $table->decimal('amount', 12, 2)->nullable()->after('monthly_amount');
        });
    }

    public function down(): void
    {
        Schema::table('licences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('licence_plan_id');
            $table->dropColumn(['room_count', 'currency', 'monthly_amount', 'amount']);
        });
    }
};
