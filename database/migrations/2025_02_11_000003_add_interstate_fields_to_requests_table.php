<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // Skip columns already added by 000002 migration
            if (!Schema::hasColumn('requests', 'delivery_mode')) {
                $table->enum('delivery_mode', ['local', 'interstate', 'international'])->default('local')->nullable();
            }
            if (!Schema::hasColumn('requests', 'trucking_company_id')) {
                $table->unsignedBigInteger('trucking_company_id')->nullable()->index();
            }
            if (!Schema::hasColumn('requests', 'origin_hub_id')) {
                $table->unsignedBigInteger('origin_hub_id')->nullable()->index();
            }
            if (!Schema::hasColumn('requests', 'destination_hub_id')) {
                $table->unsignedBigInteger('destination_hub_id')->nullable()->index();
            }
            if (!Schema::hasColumn('requests', 'supported_route_id')) {
                $table->unsignedBigInteger('supported_route_id')->nullable()->index();
            }
            if (!Schema::hasColumn('requests', 'current_leg_number')) {
                $table->integer('current_leg_number')->default(1)->nullable();
            }
            if (!Schema::hasColumn('requests', 'total_legs')) {
                $table->integer('total_legs')->default(1)->nullable();
            }
            if (!Schema::hasColumn('requests', 'local_pickup_fee')) {
                $table->decimal('local_pickup_fee', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'interstate_transport_fee')) {
                $table->decimal('interstate_transport_fee', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'local_delivery_fee')) {
                $table->decimal('local_delivery_fee', 10, 2)->nullable();
            }
            // NEW columns not in 000002
            if (!Schema::hasColumn('requests', 'hub_handling_fee')) {
                $table->decimal('hub_handling_fee', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'expected_hub_arrival')) {
                $table->timestamp('expected_hub_arrival')->nullable();
            }
            if (!Schema::hasColumn('requests', 'actual_hub_arrival')) {
                $table->timestamp('actual_hub_arrival')->nullable();
            }
            if (!Schema::hasColumn('requests', 'expected_hub_departure')) {
                $table->timestamp('expected_hub_departure')->nullable();
            }
            if (!Schema::hasColumn('requests', 'actual_hub_departure')) {
                $table->timestamp('actual_hub_departure')->nullable();
            }
            if (!Schema::hasColumn('requests', 'service_type')) {
                $table->enum('service_type', ['standard', 'express'])->default('standard')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'hub_handling_fee', 'expected_hub_arrival', 'actual_hub_arrival',
                'expected_hub_departure', 'actual_hub_departure', 'service_type',
            ]);
        });
    }
};
