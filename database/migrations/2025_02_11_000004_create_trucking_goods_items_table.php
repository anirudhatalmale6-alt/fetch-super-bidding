<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Goods items table
        if (!Schema::hasTable('trucking_goods_items')) {
            Schema::create('trucking_goods_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('request_leg_id')->nullable()->index();
                $table->unsignedBigInteger('trucking_company_id')->nullable()->index();
                $table->string('item_number')->unique()->nullable();
                $table->integer('item_index')->default(1);
                $table->string('description')->nullable();
                $table->enum('category', ['electronics', 'fashion', 'food', 'documents', 'fragile', 'general', 'perishable', 'hazardous'])->default('general');
                $table->decimal('weight_kg', 8, 2)->default(0);
                $table->decimal('length_cm', 8, 2)->default(0);
                $table->decimal('width_cm', 8, 2)->default(0);
                $table->decimal('height_cm', 8, 2)->default(0);
                $table->integer('quantity')->default(1);
                $table->decimal('volumetric_weight_kg', 8, 2)->default(0);
                $table->decimal('chargeable_weight_kg', 8, 2)->default(0);
                $table->integer('volumetric_divisor_used')->default(5000);
                $table->decimal('declared_value', 12, 2)->default(0);
                $table->boolean('is_fragile')->default(false);
                $table->boolean('requires_insurance')->default(false);
                $table->decimal('company_price_per_kg', 10, 2)->nullable();
                $table->decimal('company_base_price', 10, 2)->nullable();
                $table->decimal('company_insurance_rate', 5, 2)->nullable();
                $table->decimal('company_insurance_fee', 10, 2)->nullable();
                $table->decimal('company_total_price', 10, 2)->nullable();
                $table->json('pricing_breakdown')->nullable();
                $table->json('special_instructions')->nullable();
                $table->enum('status', ['pending_pricing', 'priced', 'in_transit', 'delivered', 'damaged', 'lost'])->default('pending_pricing');
                $table->timestamp('priced_at')->nullable();
                $table->unsignedBigInteger('priced_by')->nullable()->index();
                $table->timestamps();
                $table->index('status');
            });
        }

        // 2. Add insurance columns to trucking_companies
        Schema::table('trucking_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('trucking_companies', 'insurance_type')) {
                $table->enum('insurance_type', ['percentage_of_value', 'fixed_per_shipment', 'per_item_rate'])
                      ->default('percentage_of_value')->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'insurance_rate_percent')) {
                $table->decimal('insurance_rate_percent', 5, 2)->default(1.00)->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'insurance_fixed_amount')) {
                $table->decimal('insurance_fixed_amount', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'insurance_minimum_amount')) {
                $table->decimal('insurance_minimum_amount', 10, 2)->default(500.00)->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'insurance_maximum_amount')) {
                $table->decimal('insurance_maximum_amount', 10, 2)->default(50000.00)->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'insurance_mandatory')) {
                $table->boolean('insurance_mandatory')->default(false)->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'insurance_category_rates')) {
                $table->json('insurance_category_rates')->nullable();
            }
            if (!Schema::hasColumn('trucking_companies', 'default_price_per_kg')) {
                $table->decimal('default_price_per_kg', 10, 2)->default(1000.00)->nullable();
            }
        });

        // 3. Add financial columns to request_legs
        Schema::table('request_legs', function (Blueprint $table) {
            if (!Schema::hasColumn('request_legs', 'provider_base_price')) {
                $table->decimal('provider_base_price', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'insurance_fee')) {
                $table->decimal('insurance_fee', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'platform_commission')) {
                $table->decimal('platform_commission', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'total_leg_price')) {
                $table->decimal('total_leg_price', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'provider_payout_amount')) {
                $table->decimal('provider_payout_amount', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'provider_payout_status')) {
                $table->enum('provider_payout_status', ['pending', 'processing', 'paid', 'failed'])->default('pending')->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'provider_payout_at')) {
                $table->timestamp('provider_payout_at')->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'awaiting_confirmation', 'paid', 'refunded', 'failed'])->default('pending')->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0)->nullable();
            }
            if (!Schema::hasColumn('request_legs', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_goods_items');
    }
};
