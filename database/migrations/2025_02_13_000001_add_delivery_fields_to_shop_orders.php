<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_orders', 'delivery_type')) {
                $table->enum('delivery_type', ['metro', 'interstate'])->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'logistics_request_id')) {
                $table->unsignedBigInteger('logistics_request_id')->nullable()->index();
            }
            if (!Schema::hasColumn('shop_orders', 'delivery_status')) {
                $table->enum('delivery_status', [
                    'pending', 'logistics_request_created', 'driver_assigned',
                    'picked_up', 'in_transit', 'delivered', 'cancelled',
                ])->default('pending')->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'pickup_address')) {
                $table->text('pickup_address')->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'pickup_lat')) {
                $table->decimal('pickup_lat', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'pickup_lng')) {
                $table->decimal('pickup_lng', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'delivery_lat')) {
                $table->decimal('delivery_lat', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'delivery_lng')) {
                $table->decimal('delivery_lng', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'origin_hub_id')) {
                $table->unsignedBigInteger('origin_hub_id')->nullable()->index();
            }
            if (!Schema::hasColumn('shop_orders', 'destination_hub_id')) {
                $table->unsignedBigInteger('destination_hub_id')->nullable()->index();
            }
            if (!Schema::hasColumn('shop_orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'estimated_delivery_at')) {
                $table->timestamp('estimated_delivery_at')->nullable();
            }
            if (!Schema::hasColumn('shop_orders', 'actual_delivered_at')) {
                $table->timestamp('actual_delivered_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $cols = [
                'delivery_type', 'logistics_request_id', 'delivery_status',
                'pickup_address', 'pickup_lat', 'pickup_lng',
                'delivery_lat', 'delivery_lng', 'origin_hub_id', 'destination_hub_id',
                'delivery_fee', 'estimated_delivery_at', 'actual_delivered_at',
            ];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('shop_orders', $c));
            if ($existing) { $table->dropColumn(array_values($existing)); }
        });
    }
};
