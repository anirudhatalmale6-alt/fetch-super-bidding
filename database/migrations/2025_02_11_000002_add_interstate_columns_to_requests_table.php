<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'delivery_mode')) {
                $table->enum('delivery_mode', ['local', 'interstate'])->default('local')->nullable();
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
            if (!Schema::hasColumn('requests', 'local_pickup_fee')) {
                $table->decimal('local_pickup_fee', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'interstate_transport_fee')) {
                $table->decimal('interstate_transport_fee', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'local_delivery_fee')) {
                $table->decimal('local_delivery_fee', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('requests', 'current_leg_number')) {
                $table->integer('current_leg_number')->default(1)->nullable();
            }
            if (!Schema::hasColumn('requests', 'total_legs')) {
                $table->integer('total_legs')->default(3)->nullable();
            }
            if (!Schema::hasColumn('requests', 'bidding_timeout_at')) {
                $table->timestamp('bidding_timeout_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_mode',
                'trucking_company_id',
                'origin_hub_id',
                'destination_hub_id',
                'supported_route_id',
                'local_pickup_fee',
                'interstate_transport_fee',
                'local_delivery_fee',
                'current_leg_number',
                'total_legs',
                'bidding_timeout_at',
            ]);
        });
    }
};
