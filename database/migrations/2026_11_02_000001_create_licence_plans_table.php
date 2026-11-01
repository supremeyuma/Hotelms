<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price tiers for an annual licence.
 *
 * One licence is priced by the size of the property: a hotel is charged the
 * monthly rate of the tier its room count falls into, multiplied by the licence
 * term at checkout. The tiers are rows, not config, because the price is
 * expected to change: the platform admin edits them from the licence screen
 * without a deployment.
 *
 * Defaults are inserted here rather than in a seeder so an existing installation
 * that upgrades gets a working price list too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();

            // Room-count band. `max_rooms` is null on the open-ended top tier.
            $table->unsignedSmallInteger('min_rooms');
            $table->unsignedSmallInteger('max_rooms')->nullable();

            // Monthly list price per currency. The checkout charges the term
            // (12 months by default) at once from these monthly figures.
            $table->decimal('monthly_naira', 12, 2);
            $table->decimal('monthly_usd', 12, 2);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['min_rooms', 'max_rooms']);
        });

        $now = now();

        DB::table('licence_plans')->insert([
            [
                'name' => 'Small hotel',
                'description' => 'Up to 25 rooms',
                'min_rooms' => 1,
                'max_rooms' => 25,
                'monthly_naira' => 15000,
                'monthly_usd' => 50,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Mid-size hotel',
                'description' => '26 to 50 rooms',
                'min_rooms' => 26,
                'max_rooms' => 50,
                'monthly_naira' => 25000,
                'monthly_usd' => 70,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Large hotel / multi-property',
                'description' => 'More than 50 rooms',
                'min_rooms' => 51,
                'max_rooms' => null,
                'monthly_naira' => 40000,
                'monthly_usd' => 100,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_plans');
    }
};
